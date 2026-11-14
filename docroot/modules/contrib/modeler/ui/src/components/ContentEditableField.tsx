/**
 * ContentEditableField - Rich text input component with token support
 * 
 * Provides a contenteditable div that supports:
 * - Token display and editing (e.g., "[user:name]" shown as styled pills)
 * - Drag-and-drop token insertion from token browser
 * - Copy/paste with token preservation
 * - Single-line and multi-line modes
 */

import React, { useState, useCallback, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { FiEdit2 } from 'react-icons/fi';
import { TIMING, UI_DIMENSIONS } from '../constants/dimensions';
import { sanitizeTokenHtml, escapeHtml } from '../utils/sanitize';
import {
  convertTokensToHTML,
  convertHTMLToTokens,
  createTokenElement,
  isTokenElement,
  parseTokenFromDragEvent,
} from '../utils/tokenUtils';
import { t } from '../utils/translation';
import TokenPicker from './TokenPicker';
import { useTokenSources } from './TokenSourceContext';

interface TokenEditState {
  element: HTMLElement;
  token: string;
  x: number;
  y: number;
  position: 'above' | 'below';
}

interface TokenIconState {
  x: number;
  y: number;
  element: HTMLElement;
}

interface DropCursor {
  x: number;
  y: number;
  height: number;
}

interface TokenPickerState {
  /** Position relative to the wrapper, in px. */
  x: number;
  y: number;
}

interface ContentEditableFieldProps {
  value: string;
  onChange: (value: string) => void;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
  multiline?: boolean;
  /** Whether this field accepts token drops. Defaults to true. */
  acceptsTokens?: boolean;
  /**
   * Id of an element that names this field. A contenteditable div is not a
   * labelable element, so a surrounding `<label htmlFor>` never reaches it;
   * callers that render their own label wire it up through this instead.
   */
  ariaLabelledBy?: string;
}

/**
 * Get caret position from coordinates using browser APIs
 */
function getCaretPositionFromPoint(x: number, y: number, container: HTMLElement): Range | null {
  let insertPosition: Range | null = null;

  // Use document.caretPositionFromPoint (Firefox) or document.caretRangeFromPoint (Chrome/Safari)
  if ((document as any).caretPositionFromPoint) {
    const caretPosition = (document as any).caretPositionFromPoint(x, y);
    if (caretPosition && container.contains(caretPosition.offsetNode)) {
      const range = document.createRange();
      range.setStart(caretPosition.offsetNode, caretPosition.offset);
      range.setEnd(caretPosition.offsetNode, caretPosition.offset);
      insertPosition = range;
    }
  } else if ((document as any).caretRangeFromPoint) {
    const caretRange = (document as any).caretRangeFromPoint(x, y);
    if (caretRange && container.contains(caretRange.startContainer)) {
      insertPosition = caretRange;
    }
  }

  return insertPosition;
}

/**
 * Get fallback insertion position (current selection or end of container)
 */
function getFallbackInsertPosition(container: HTMLElement): Range {
  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    if (container === range.commonAncestorContainer ||
        container.contains(range.commonAncestorContainer)) {
      return range;
    }
  }

  // Final fallback: insert at the end
  const range = document.createRange();
  if (container.firstChild) {
    range.setStartAfter(container.lastChild || container);
    range.setEndAfter(container.lastChild || container);
  } else {
    range.selectNodeContents(container);
    range.collapse(false);
  }
  return range;
}

/** Presentation-only caret boundary around non-editable token pills. */
const ZERO_WIDTH_SPACE = '\u200B';

interface LogicalSelection {
  start: number;
  end: number;
}

interface DomPoint {
  node: Node;
  offset: number;
}

interface SerializedMetrics {
  length: number;
  endsWithNewline: boolean;
  meaningful: boolean;
}

const CONTENTEDITABLE_BLOCK_TAGS: Record<string, true> = {
  DIV: true,
  P: true,
};

function isContenteditableBlock(node: Node): node is Element {
  return node instanceof Element && CONTENTEDITABLE_BLOCK_TAGS[node.tagName] === true;
}

function serializedChildrenMetrics(node: Node, limit = node.childNodes.length): SerializedMetrics {
  let length = 0;
  let started = false;
  let endsWithNewline = false;
  let lastWasEmptyBlock = false;

  for (let index = 0; index < limit && index < node.childNodes.length; index++) {
    const child = node.childNodes[index];
    const childMetrics = serializedNodeMetrics(child);
    const isBlock = isContenteditableBlock(child);
    if (isBlock && started && (!endsWithNewline || lastWasEmptyBlock)) {
      length++;
      endsWithNewline = true;
    }
    length += childMetrics.length;
    if (childMetrics.length > 0) endsWithNewline = childMetrics.endsWithNewline;
    if (isBlock || childMetrics.meaningful) started = true;
    lastWasEmptyBlock = isBlock && childMetrics.length === 0;
  }

  return { length, endsWithNewline, meaningful: started };
}

/**
 * Length and line-boundary state in the serialized field value. Pills count as
 * their opaque raw token string; native contenteditable blocks count the same
 * implicit newlines emitted by convertHTMLToTokens.
 */
function serializedNodeMetrics(node: Node): SerializedMetrics {
  if (node.nodeType === Node.TEXT_NODE) {
    const text = (node.textContent || '').replace(/\u200B/g, '');
    return {
      length: text.length,
      endsWithNewline: text.endsWith('\n'),
      meaningful: text.length > 0,
    };
  }
  if (isTokenElement(node)) {
    const token = node.getAttribute('data-token') || node.textContent || '';
    return {
      length: token.length,
      endsWithNewline: token.endsWith('\n'),
      meaningful: token.length > 0,
    };
  }
  if (node instanceof HTMLBRElement) {
    const parent = node.parentNode;
    const isPlaceholder =
      !!parent &&
      isContenteditableBlock(parent) &&
      parent.childNodes.length === 1;
    return isPlaceholder
      ? { length: 0, endsWithNewline: false, meaningful: false }
      : { length: 1, endsWithNewline: true, meaningful: true };
  }

  const children = serializedChildrenMetrics(node);
  return {
    ...children,
    meaningful: isContenteditableBlock(node) || children.meaningful,
  };
}

function serializedNodeLength(node: Node): number {
  return serializedNodeMetrics(node).length;
}

function captureLogicalOffset(
  container: HTMLElement,
  targetNode: Node,
  targetOffset: number,
): number | null {
  if (targetNode !== container && !container.contains(targetNode)) return null;

  let offset = 0;
  let found = false;
  const walk = (node: Node): void => {
    if (found) return;
    if (node === targetNode) {
      if (node.nodeType === Node.TEXT_NODE) {
        offset += (node.textContent || '').slice(0, targetOffset).replace(/\u200B/g, '').length;
      } else if (isTokenElement(node)) {
        if (targetOffset > 0) offset += serializedNodeLength(node);
      } else {
        offset += serializedChildrenMetrics(node, targetOffset).length;
      }
      found = true;
      return;
    }
    if (
      node.nodeType === Node.TEXT_NODE ||
      isTokenElement(node) ||
      node instanceof HTMLBRElement
    ) {
      offset += serializedNodeLength(node);
      return;
    }

    let started = false;
    let endsWithNewline = false;
    let lastWasEmptyBlock = false;
    for (const child of Array.from(node.childNodes)) {
      const childMetrics = serializedNodeMetrics(child);
      const isBlock = isContenteditableBlock(child);
      if (isBlock && started && (!endsWithNewline || lastWasEmptyBlock)) {
        offset++;
        endsWithNewline = true;
      }
      walk(child);
      if (found) return;
      if (childMetrics.length > 0) endsWithNewline = childMetrics.endsWithNewline;
      if (isBlock || childMetrics.meaningful) started = true;
      lastWasEmptyBlock = isBlock && childMetrics.length === 0;
    }
  };

  walk(container);
  return found ? offset : null;
}

function captureLogicalSelection(container: HTMLElement): LogicalSelection | null {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return null;
  const range = selection.getRangeAt(0);
  const start = captureLogicalOffset(container, range.startContainer, range.startOffset);
  const end = captureLogicalOffset(container, range.endContainer, range.endOffset);
  return start === null || end === null ? null : { start, end };
}

function rawTextOffset(text: string, visibleOffset: number): number {
  let rawOffset = 0;
  let visible = 0;
  while (rawOffset < text.length && visible < visibleOffset) {
    if (text[rawOffset] !== ZERO_WIDTH_SPACE) visible++;
    rawOffset++;
  }
  return rawOffset;
}

function resolveLogicalOffset(container: HTMLElement, target: number): DomPoint {
  let remaining = Math.max(0, target);
  let result: DomPoint | null = null;
  let last: DomPoint = { node: container, offset: 0 };

  const walk = (node: Node): void => {
    if (result) return;
    if (node.nodeType === Node.TEXT_NODE) {
      const text = node.textContent || '';
      const length = serializedNodeLength(node);
      last = { node, offset: rawTextOffset(text, length) };
      if (remaining <= length) {
        result = { node, offset: rawTextOffset(text, remaining) };
      } else {
        remaining -= length;
      }
      return;
    }
    if (isTokenElement(node) || node instanceof HTMLBRElement) {
      const parent = node.parentNode;
      if (!parent) return;
      const nodeIndex = Array.from(parent.childNodes).findIndex(child => child === node);
      const length = serializedNodeLength(node);
      if (remaining === 0) {
        result = { node: parent, offset: nodeIndex };
      } else if (remaining <= length) {
        result = { node: parent, offset: nodeIndex + 1 };
      } else {
        remaining -= length;
        last = { node: parent, offset: nodeIndex + 1 };
      }
      return;
    }

    let started = false;
    let endsWithNewline = false;
    let lastWasEmptyBlock = false;
    const children = Array.from(node.childNodes);
    for (let index = 0; index < children.length; index++) {
      const child = children[index];
      const childMetrics = serializedNodeMetrics(child);
      const isBlock = isContenteditableBlock(child);
      if (isBlock && started && (!endsWithNewline || lastWasEmptyBlock)) {
        if (remaining === 0) {
          result = { node, offset: index };
          return;
        }
        remaining--;
        endsWithNewline = true;
      }
      walk(child);
      if (result) return;
      if (childMetrics.length > 0) endsWithNewline = childMetrics.endsWithNewline;
      if (isBlock || childMetrics.meaningful) started = true;
      lastWasEmptyBlock = isBlock && childMetrics.length === 0;
    }

    if (remaining === 0) {
      result = { node, offset: node.childNodes.length };
    }
  };

  walk(container);
  return result ?? last;
}

/**
 * Give every token a native text caret position on both sides. One shared
 * boundary node is sufficient between adjacent pills. These sentinels are
 * presentation-only and are removed by convertHTMLToTokens.
 */
function normalizeTokenCaretBoundaries(container: HTMLElement): void {
  const textNodes: Text[] = [];
  const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
  let current = walker.nextNode();
  while (current) {
    textNodes.push(current as Text);
    current = walker.nextNode();
  }
  textNodes.forEach(textNode => {
    if (textNode.data.includes(ZERO_WIDTH_SPACE)) {
      textNode.data = textNode.data.replace(/\u200B/g, '');
    }
  });

  const tokens = Array.from(container.querySelectorAll('.config-token'));
  tokens.forEach(token => {
    const parent = token.parentNode;
    if (!parent) return;

    const previous = token.previousSibling;
    if (previous?.nodeType === Node.TEXT_NODE) {
      const text = previous as Text;
      if (!text.data.endsWith(ZERO_WIDTH_SPACE)) text.appendData(ZERO_WIDTH_SPACE);
    } else {
      parent.insertBefore(document.createTextNode(ZERO_WIDTH_SPACE), token);
    }

    const next = token.nextSibling;
    if (next?.nodeType === Node.TEXT_NODE) {
      const text = next as Text;
      if (!text.data.startsWith(ZERO_WIDTH_SPACE)) text.insertData(0, ZERO_WIDTH_SPACE);
    } else {
      parent.insertBefore(document.createTextNode(ZERO_WIDTH_SPACE), token.nextSibling);
    }
  });
}

function adjacentTokenAtCaret(
  container: HTMLElement,
  range: Range,
  direction: 'backward' | 'forward',
): Element | null {
  if (!range.collapsed ||
      (range.startContainer !== container && !container.contains(range.startContainer))) {
    return null;
  }

  const node = range.startContainer;
  let sibling: Node | null;
  if (node.nodeType === Node.TEXT_NODE) {
    const text = node.textContent || '';
    const presentationOnly = direction === 'backward'
      ? text.slice(0, range.startOffset)
      : text.slice(range.startOffset);
    if (presentationOnly.replace(/\u200B/g, '') !== '') return null;
    sibling = direction === 'backward' ? node.previousSibling : node.nextSibling;
  } else {
    sibling = (direction === 'backward'
      ? node.childNodes[range.startOffset - 1]
      : node.childNodes[range.startOffset]) ?? null;
  }

  // Range insertion and token deletion can leave multiple empty text nodes
  // around the one presentation boundary. They are the same caret boundary,
  // not content that should block navigation to the next pill.
  while (
    sibling?.nodeType === Node.TEXT_NODE &&
    (sibling.textContent || '').replace(/\u200B/g, '') === ''
  ) {
    sibling = direction === 'backward' ? sibling.previousSibling : sibling.nextSibling;
  }
  return isTokenElement(sibling) ? sibling : null;
}

function placeCaretAdjacentToToken(token: Element, direction: 'before' | 'after'): void {
  const parent = token.parentNode;
  const selection = window.getSelection();
  if (!parent || !selection) return;

  const range = document.createRange();
  const boundary = direction === 'before' ? token.previousSibling : token.nextSibling;
  if (boundary?.nodeType === Node.TEXT_NODE) {
    const text = boundary.textContent || '';
    const offset = direction === 'before'
      ? Math.max(0, text.length - (text.endsWith(ZERO_WIDTH_SPACE) ? 1 : 0))
      : (text.startsWith(ZERO_WIDTH_SPACE) ? 1 : 0);
    range.setStart(boundary, offset);
  } else if (direction === 'before') {
    range.setStartBefore(token);
  } else {
    range.setStartAfter(token);
  }
  range.collapse(true);
  selection.removeAllRanges();
  selection.addRange(range);
}

const ContentEditableField: React.FC<ContentEditableFieldProps> = ({
  value,
  onChange,
  className = '',
  placeholder,
  disabled = false,
  multiline = false,
  acceptsTokens = true,
  ariaLabelledBy,
}) => {
  const divRef = useRef<HTMLDivElement>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const debounceTimeoutRef = useRef<number | null>(null);
  const dragSourceRef = useRef<HTMLElement | null>(null);
  const editInputRef = useRef<HTMLInputElement>(null);
  const [isEditing, setIsEditing] = useState(false);
  const [isDragOver, setIsDragOver] = useState(false);
  const [dropCursor, setDropCursor] = useState<DropCursor | null>(null);
  const [_localValue, setLocalValue] = useState(value || '');
  const [editIconTarget, setEditIconTarget] = useState<TokenIconState | null>(null);
  const [editingToken, setEditingToken] = useState<TokenEditState | null>(null);
  // "[" token-picker popup state (null when closed).
  const [tokenPicker, setTokenPicker] = useState<TokenPickerState | null>(null);
  // Picker insertion is tracked in serialized-value offsets rather than DOM
  // node identity. Text nodes are routinely split/re-created by contenteditable
  // and React, while token labels have a different length from raw token strings.
  const triggerRangeRef = useRef<LogicalSelection | null>(null);
  const consumedBracketRef = useRef<number | null>(null);
  const restoreCaretRef = useRef<number | null>(null);

  // The picker reads its data from this shared context (provided by
  // PropertyPanel). We only need `onPickerOpenChange` here so PropertyPanel can
  // FREEZE its review chrome while the picker is open. It is undefined for the
  // many non-picker usages of this field (ConfigurationForm, etc.) — where the
  // reporter below is a no-op.
  const { onPickerOpenChange } = useTokenSources();

  // EVENT-DRIVEN picker-open signal (NOT an effect-cleanup signal).
  //
  // The freeze must release ONLY on a genuine picker close. Reporting `false`
  // from an effect CLEANUP is wrong: the cleanup re-runs on every remount /
  // dependency change caused by the volatile token-source context churning as
  // step data loads — which momentarily flips the freeze off and lets a LIVE
  // (unfrozen) render flash the panel. So we report edge-triggered:
  //   • `true` ONLY on the genuine open transition (closed → open), in
  //     updateTokenPickerFromCaret where the picker is actually shown.
  //   • `false` ONLY from closeTokenPicker (the single genuine-close path:
  //     insert / × / Escape / backdrop / caret moved past "[").
  // A ref dedupes repeated reports (the open path also runs on every keystroke
  // while already open). `onPickerOpenChange` is kept in a ref so the open/close
  // callbacks stay stable and don't themselves churn.
  const pickerReportedOpenRef = useRef(false);
  const onPickerOpenChangeRef = useRef(onPickerOpenChange);
  onPickerOpenChangeRef.current = onPickerOpenChange;
  const reportPickerOpen = useCallback((open: boolean) => {
    if (pickerReportedOpenRef.current === open) return;
    pickerReportedOpenRef.current = open;
    onPickerOpenChangeRef.current?.(open);
  }, []);

  // If the field genuinely UNMOUNTS while the picker was reported open (e.g. the
  // user navigates to another node so the field is destroyed), the picker is
  // truly gone — report closed once on real unmount only. This cleanup runs on
  // unmount; it does NOT re-run on data churn because its dep array is empty.
  useEffect(() => {
    return () => {
      if (pickerReportedOpenRef.current) {
        pickerReportedOpenRef.current = false;
        onPickerOpenChangeRef.current?.(false);
      }
    };
  }, []);

  // Sync external value to internal state when not editing
  useEffect(() => {
    if (divRef.current && !isEditing) {
      const incomingValue = value || '';
      // Only convert bracket syntax to token pills when the field accepts tokens
      const htmlContent = acceptsTokens ? convertTokensToHTML(incomingValue) : incomingValue;
      const currentContent = divRef.current.innerHTML || '';

      if (currentContent !== htmlContent) {
        divRef.current.innerHTML = htmlContent;
        if (acceptsTokens) normalizeTokenCaretBoundaries(divRef.current);
        setLocalValue(htmlContent);
      }
    }
  }, [value, isEditing, acceptsTokens]);

  // Track selection changes to highlight selected token spans as whole pills
  useEffect(() => {
    const container = divRef.current;
    if (!container) return;

    const updateTokenSelection = () => {
      const tokens = container.querySelectorAll('.config-token');
      const selection = window.getSelection();

      if (!selection || selection.rangeCount === 0) {
        tokens.forEach(tok => tok.classList.remove('selected'));
        return;
      }

      const range = selection.getRangeAt(0);

      tokens.forEach(tok => {
        // A token is selected if the selection range intersects it
        const isSelected = range.intersectsNode(tok) && !range.collapsed;
        tok.classList.toggle('selected', isSelected);
      });
    };

    document.addEventListener('selectionchange', updateTokenSelection);
    return () => {
      document.removeEventListener('selectionchange', updateTokenSelection);
    };
  }, []);

  // Compute edit icon position relative to the wrapper
  const getTokenIconPosition = useCallback((tokenEl: HTMLElement): { x: number; y: number } | null => {
    const wrapper = wrapperRef.current;
    if (!wrapper) return null;
    const tokenRect = tokenEl.getBoundingClientRect();
    const wrapperRect = wrapper.getBoundingClientRect();
    return {
      x: tokenRect.left - wrapperRect.left + tokenRect.width / 2,
      y: tokenRect.top - wrapperRect.top,
    };
  }, []);

  // Track mouseover/mouseout on the wrapper to show edit icon
  // Using the wrapper (not the contenteditable container) so the icon stays visible
  // when the mouse moves from the token to the edit icon (both are inside the wrapper).
  useEffect(() => {
    const wrapper = wrapperRef.current;
    const container = divRef.current;
    if (!wrapper || !container || disabled) return;

    const handleMouseOver = (e: MouseEvent) => {
      if (editingToken) return; // Don't move icon while editing
      const target = (e.target as HTMLElement).closest?.('.config-token') as HTMLElement | null;
      if (target && container.contains(target)) {
        const pos = getTokenIconPosition(target);
        if (pos) {
          setEditIconTarget({ x: pos.x, y: pos.y, element: target });
        }
      }
    };

    const handleMouseOut = (e: MouseEvent) => {
      if (editingToken) return;
      const relatedTarget = e.relatedTarget as HTMLElement | null;
      // Only hide if the mouse is leaving the wrapper entirely
      if (relatedTarget && wrapper.contains(relatedTarget)) return;
      setEditIconTarget(null);
    };

    wrapper.addEventListener('mouseover', handleMouseOver);
    wrapper.addEventListener('mouseout', handleMouseOut);
    return () => {
      wrapper.removeEventListener('mouseover', handleMouseOver);
      wrapper.removeEventListener('mouseout', handleMouseOut);
    };
  }, [disabled, editingToken, getTokenIconPosition]);

  // Also show edit icon for selected tokens (keyboard navigation)
  useEffect(() => {
    if (editingToken || disabled) return;
    const container = divRef.current;
    if (!container) return;

    const updateEditIconForSelection = () => {
      const selectedToken = container.querySelector('.config-token.selected') as HTMLElement | null;
      if (selectedToken) {
        const pos = getTokenIconPosition(selectedToken);
        if (pos) {
          setEditIconTarget({ x: pos.x, y: pos.y, element: selectedToken });
        }
      }
    };

    document.addEventListener('selectionchange', updateEditIconForSelection);
    return () => {
      document.removeEventListener('selectionchange', updateEditIconForSelection);
    };
  }, [disabled, editingToken, getTokenIconPosition]);

  // Open the token edit popup
  const openTokenEdit = useCallback((tokenEl: HTMLElement) => {
    const wrapper = wrapperRef.current;
    if (!wrapper) return;
    const tokenRect = tokenEl.getBoundingClientRect();
    const wrapperRect = wrapper.getBoundingClientRect();
    const token = tokenEl.getAttribute('data-token') || '';
    // Strip brackets for editing
    const tokenValue = token.startsWith('[') && token.endsWith(']')
      ? token.slice(1, -1)
      : token;
    // Check if there is enough space above the token for the popup (~80px)
    const spaceAbove = tokenRect.top - wrapperRect.top;
    const position = spaceAbove < 80 ? 'below' : 'above';
    // Clamp horizontal position so the popup doesn't overflow left/right
    const popupWidth = 200; // matches CSS min-width
    const centerX = tokenRect.left - wrapperRect.left + tokenRect.width / 2;
    const halfPopup = popupWidth / 2;
    const clampedX = Math.max(halfPopup, Math.min(centerX, wrapperRect.width - halfPopup));
    setEditingToken({
      element: tokenEl,
      token: tokenValue,
      x: clampedX,
      y: position === 'above'
        ? tokenRect.top - wrapperRect.top
        : tokenRect.bottom - wrapperRect.top,
      position,
    });
    setEditIconTarget(null);
    // Focus the input after render
    requestAnimationFrame(() => {
      editInputRef.current?.focus();
      editInputRef.current?.select();
    });
  }, []);

  // Ref tracking the latest onChange callback so the unmount cleanup (which
  // has an empty dependency array) always calls the current version.
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  // Ref holding the raw value that is pending in the debounce timer.
  // Set every time debouncedOnChange is called; cleared when the timer fires.
  const pendingValueRef = useRef<string | null>(null);

  // Flush pending changes and cleanup timeout on unmount.
  useEffect(() => {
    return () => {
      if (debounceTimeoutRef.current) {
        clearTimeout(debounceTimeoutRef.current);
        debounceTimeoutRef.current = null;
        // Flush the pending value so no edits are lost.
        if (pendingValueRef.current !== null) {
          const tokenValue = convertHTMLToTokens(pendingValueRef.current);
          onChangeRef.current(tokenValue);
          pendingValueRef.current = null;
        }
      }
    };
  }, []);

  const debouncedOnChange = useCallback((newValue: string) => {
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
    }

    pendingValueRef.current = newValue;
    debounceTimeoutRef.current = setTimeout(() => {
      const tokenValue = convertHTMLToTokens(newValue);
      onChange(tokenValue);
      pendingValueRef.current = null;
    }, TIMING.DEBOUNCE_DELAY) as unknown as number;
  }, [onChange]);

  // Save the edited token
  const saveTokenEdit = useCallback(() => {
    if (!editingToken || !divRef.current) return;
    const newToken = editInputRef.current?.value?.trim() || '';
    if (!newToken) {
      setEditingToken(null);
      return;
    }
    const wrappedToken = `[${newToken}]`;
    const label = newToken.split(':').pop() || newToken;
    editingToken.element.setAttribute('data-token', wrappedToken);
    editingToken.element.setAttribute('title', t('Token: @token', { '@token': wrappedToken }));
    editingToken.element.textContent = label;
    setEditingToken(null);

    // Trigger change immediately (not debounced) so the parent gets the update
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
      debounceTimeoutRef.current = null;
    }
    pendingValueRef.current = null;
    const htmlContent = divRef.current.innerHTML || '';
    setLocalValue(htmlContent);
    const tokenValue = convertHTMLToTokens(htmlContent);
    onChange(tokenValue);
  }, [editingToken, onChange]);

  // Cancel token editing
  const cancelTokenEdit = useCallback(() => {
    setEditingToken(null);
  }, []);

  // Handle keydown in the edit popup input
  const handleEditInputKeyDown = useCallback((e: React.KeyboardEvent<HTMLInputElement>) => {
    e.stopPropagation();
    if (e.key === 'Enter') {
      e.preventDefault();
      saveTokenEdit();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      cancelTokenEdit();
    }
  }, [saveTokenEdit, cancelTokenEdit]);

  const closeTokenPicker = useCallback((restoreFocus = false) => {
    reportPickerOpen(false);
    setTokenPicker(null);
    if (triggerRangeRef.current) {
      consumedBracketRef.current = triggerRangeRef.current.start;
    }
    triggerRangeRef.current = null;

    if (restoreFocus) {
      const container = divRef.current;
      const logicalOffset = restoreCaretRef.current;
      if (container) {
        container.focus();
        try {
          const selection = window.getSelection();
          if (selection && logicalOffset !== null) {
            const point = resolveLogicalOffset(container, logicalOffset);
            const range = document.createRange();
            range.setStart(point.node, point.offset);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
          }
        } catch {
          // Some test/browser Range implementations only support focus.
        }
      }
    }
    restoreCaretRef.current = null;
  }, [reportPickerOpen]);

  /**
   * Open the picker for the nearest "[" before a collapsed field caret. The
   * trigger and caret are stored as serialized offsets so later focus changes
   * and DOM node replacement cannot change what will be replaced.
   */
  const updateTokenPickerFromCaret = useCallback(() => {
    const container = divRef.current;
    const wrapper = wrapperRef.current;
    if (!container || !wrapper || disabled || !acceptsTokens) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const range = selection.getRangeAt(0);
    const node = range.startContainer;
    if (node.nodeType !== Node.TEXT_NODE || !container.contains(node)) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const text = node.textContent || '';
    const caret = range.startOffset;
    const bracketIndex = text.lastIndexOf('[', caret - 1);
    if (bracketIndex === -1) {
      consumedBracketRef.current = null;
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const bracketOffset = captureLogicalOffset(container, node, bracketIndex);
    const logicalSelection = captureLogicalSelection(container);
    if (bracketOffset === null || !logicalSelection) {
      if (tokenPicker) closeTokenPicker();
      return;
    }
    if (consumedBracketRef.current === bracketOffset) return;

    const query = text.slice(bracketIndex + 1, caret);
    if (/\s/.test(query)) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    triggerRangeRef.current = {
      start: bracketOffset,
      end: logicalSelection.end,
    };
    if (tokenPicker) return;

    restoreCaretRef.current = logicalSelection.end;

    let x = 0;
    let y = 0;
    if (typeof range.getBoundingClientRect === 'function') {
      const caretRect = range.getBoundingClientRect();
      x = caretRect.left;
      y = caretRect.bottom;
    }

    reportPickerOpen(true);
    setTokenPicker({ x: Math.max(0, x), y: Math.max(0, y) });
  }, [disabled, acceptsTokens, tokenPicker, closeTokenPicker, reportPickerOpen]);

  const handleInput = useCallback(() => {
    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      // Always pass the edited DOM through convertHTMLToTokens. Plain text is
      // unchanged, while native multiline block boundaries become newlines.
      debouncedOnChange(htmlContent);
    }
    // After the DOM updates, re-evaluate whether the "[" picker should show.
    updateTokenPickerFromCaret();
  }, [debouncedOnChange, updateTokenPickerFromCaret]);

  /**
   * Replace the saved trigger/query range with a token. The saved range uses
   * serialized offsets, so picker focus and text-node replacement are irrelevant
   * and text after the trigger remains untouched.
   */
  const handleTokenPickerSelect = useCallback((label: string, token: string) => {
    const container = divRef.current;
    const trigger = triggerRangeRef.current;
    if (!container || !trigger) {
      closeTokenPicker();
      return;
    }

    const start = resolveLogicalOffset(container, trigger.start);
    const end = resolveLogicalOffset(container, trigger.end);
    const insertRange = document.createRange();
    insertRange.setStart(start.node, start.offset);
    insertRange.setEnd(end.node, end.offset);

    // If the field changed while the modal was open, do not guess at another
    // bracket. Inserting at a guessed position risks deleting unrelated suffixes.
    const triggerText = insertRange.toString().replace(/\u200B/g, '');
    if (!triggerText.startsWith('[')) {
      closeTokenPicker(true);
      return;
    }

    insertRange.deleteContents();
    insertRange.collapse(true);
    const tokenElement = createTokenElement(label, token);
    insertRange.insertNode(tokenElement);
    normalizeTokenCaretBoundaries(container);
    placeCaretAdjacentToToken(tokenElement, 'after');

    closeTokenPicker();
    consumedBracketRef.current = null;

    const htmlContent = container.innerHTML || '';
    setLocalValue(htmlContent);
    debouncedOnChange(htmlContent);
  }, [closeTokenPicker, debouncedOnChange]);

  const handlePaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault();

    const plainText = e.clipboardData.getData('text/plain');
    const htmlText = e.clipboardData.getData('text/html');

    // Prefer sanitized HTML if it contains tokens, otherwise use plain text
    let contentToInsert: string;
    if (htmlText && htmlText.includes('config-token')) {
      contentToInsert = sanitizeTokenHtml(htmlText);
    } else {
      contentToInsert = escapeHtml(plainText);
    }

    // Insert at cursor position
    const selection = window.getSelection();
    if (selection && selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();

      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = contentToInsert;

      const fragment = document.createDocumentFragment();
      while (tempDiv.firstChild) {
        fragment.appendChild(tempDiv.firstChild);
      }
      range.insertNode(fragment);

      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }

    if (divRef.current) {
      const caret = captureLogicalSelection(divRef.current);
      normalizeTokenCaretBoundaries(divRef.current);
      if (selection && caret) {
        const point = resolveLogicalOffset(divRef.current, caret.end);
        const range = document.createRange();
        range.setStart(point.node, point.offset);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      }
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      debouncedOnChange(htmlContent);
    }
  }, [debouncedOnChange]);

  const handleFocus = useCallback(() => {
    setIsEditing(true);
  }, []);

  const handleBlur = useCallback(() => {
    // The "[" token picker is a MODAL dialog rendered through a portal; it owns
    // its own lifecycle (close on token insert / × / Escape / backdrop click).
    // Field focus is therefore IRRELEVANT to the picker — blur must NOT touch
    // it. This handler only persists the field value on blur.
    setIsEditing(false);

    // Cancel any pending debounce — we save synchronously below.
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
      debounceTimeoutRef.current = null;
    }
    pendingValueRef.current = null;

    // Always persist the current DOM content on blur so that values typed
    // just before a Tab / click-away are never lost.  handleInput already
    // updates localValue on every keystroke, so comparing would skip onChange.
    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      const tokenValue = convertHTMLToTokens(htmlContent);
      onChange(tokenValue);
    }
  }, [onChange]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    const hasNavigationModifier = e.shiftKey || e.ctrlKey || e.metaKey || e.altKey;

    // The picker owns plain navigation keys. Modified arrows retain the
    // browser's selection and word/line navigation behavior.
    if (tokenPicker) {
      const plainPickerArrow =
        !hasNavigationModifier &&
        (e.key === 'ArrowDown' || e.key === 'ArrowUp' ||
          e.key === 'ArrowLeft' || e.key === 'ArrowRight');
      if (e.key === 'Enter' || e.key === 'Escape' || plainPickerArrow) {
        e.preventDefault();
        return;
      }
    }

    // Prevent Enter in single-line mode
    if (!multiline && e.key === 'Enter') {
      e.preventDefault();
      divRef.current?.blur();
      return;
    }

    if (!hasNavigationModifier && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
      const container = divRef.current;
      const selection = window.getSelection();
      if (container && selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        const direction = e.key === 'ArrowLeft' ? 'backward' : 'forward';
        const token = adjacentTokenAtCaret(container, range, direction);
        if (token) {
          e.preventDefault();
          placeCaretAdjacentToToken(token, direction === 'backward' ? 'before' : 'after');
          return;
        }
      }
    }

    // Ctrl+E to edit the selected or adjacent token
    if (e.key === 'e' && (e.ctrlKey || e.metaKey)) {
      const container = divRef.current;
      if (!container) return;

      // Find the selected token or the token adjacent to the cursor
      const selectedToken = container.querySelector('.config-token.selected') as HTMLElement | null;
      if (selectedToken) {
        e.preventDefault();
        openTokenEdit(selectedToken);
        return;
      }

      const selection = window.getSelection();
      if (selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        const adjacentToken =
          adjacentTokenAtCaret(container, range, 'forward') ||
          adjacentTokenAtCaret(container, range, 'backward');
        if (adjacentToken) {
          e.preventDefault();
          openTokenEdit(adjacentToken as HTMLElement);
          return;
        }
      }
    }

    if (e.key === 'Delete' || e.key === 'Backspace') {
      const container = divRef.current;
      const selection = window.getSelection();
      if (container && selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        const direction = e.key === 'Backspace' ? 'backward' : 'forward';
        const tokenToDelete = adjacentTokenAtCaret(container, range, direction);
        if (tokenToDelete) {
          e.preventDefault();
          const parent = tokenToDelete.parentNode;
          if (!parent) return;

          // A logical offset at a block boundary is ambiguous: the same number
          // can be the end of the previous line or the start of this one. Keep
          // an exact DOM marker through normalization, then replace it with a
          // concrete presentation character. Chromium does not keep typing on
          // an empty final line when the selection only targets an empty node.
          const anchorMarker = document.createComment('token-caret');
          parent.insertBefore(anchorMarker, tokenToDelete);
          tokenToDelete.remove();
          normalizeTokenCaretBoundaries(container);
          const caretAnchor = document.createTextNode(ZERO_WIDTH_SPACE);
          parent.replaceChild(caretAnchor, anchorMarker);

          const nextRange = document.createRange();
          nextRange.setStart(caretAnchor, 1);
          nextRange.collapse(true);
          selection.removeAllRanges();
          selection.addRange(nextRange);

          const htmlContent = container.innerHTML || '';
          setLocalValue(htmlContent);
          debouncedOnChange(htmlContent);
          return;
        }
      }
    }
  }, [multiline, debouncedOnChange, openTokenEdit, tokenPicker]);

  const handleDragStart = useCallback((e: React.DragEvent) => {
    const target = e.target as HTMLElement;
    if (!isTokenElement(target)) return;

    const token = target.getAttribute('data-token') || '';
    const label = target.textContent || '';

    e.dataTransfer.setData('application/token', JSON.stringify({ label, token }));
    e.dataTransfer.setData('text/plain', token);
    e.dataTransfer.effectAllowed = 'move';
    dragSourceRef.current = target;
  }, []);

  const handleDragEnd = useCallback(() => {
    dragSourceRef.current = null;
  }, []);

  const handleDragEnter = useCallback((e: React.DragEvent) => {
    if (disabled) return;
    e.preventDefault();
    if (!acceptsTokens) {
      if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'none';
      }
      return;
    }
    setIsDragOver(true);
  }, [disabled, acceptsTokens]);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    if (disabled) return;
    e.preventDefault();
    if (!e.currentTarget.contains(e.relatedTarget as Node)) {
      setIsDragOver(false);
      setDropCursor(null);
    }
  }, [disabled]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    if (disabled || !divRef.current) return;
    e.preventDefault();
    if (!acceptsTokens) {
      e.dataTransfer.dropEffect = 'none';
      return;
    }
    e.dataTransfer.dropEffect = dragSourceRef.current ? 'move' : 'copy';

    const insertPosition = getCaretPositionFromPoint(e.clientX, e.clientY, divRef.current);

    if (insertPosition) {
      const tempSpan = document.createElement('span');
      tempSpan.style.position = 'absolute';
      tempSpan.style.visibility = 'hidden';
      tempSpan.style.height = '1px';
      tempSpan.style.width = '1px';

      try {
        insertPosition.insertNode(tempSpan);
        const rect = tempSpan.getBoundingClientRect();
        const fieldRect = divRef.current.getBoundingClientRect();

        tempSpan.remove();

        setDropCursor({
          x: rect.left - fieldRect.left,
          y: rect.top - fieldRect.top,
          height: Math.max(rect.height, 16)
        });
      } catch (_error) {
        if (tempSpan.parentNode) {
          tempSpan.remove();
        }
        setDropCursor(null);
      }
    } else {
      setDropCursor(null);
    }
  }, [disabled, acceptsTokens]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    if (disabled || !divRef.current || !acceptsTokens) return;
    e.preventDefault();
    setIsDragOver(false);
    setDropCursor(null);

    const tokenData = parseTokenFromDragEvent(e.dataTransfer);
    if (!tokenData) return;

    // For internal moves, remove the source token before inserting at new position
    const sourceElement = dragSourceRef.current;
    if (sourceElement && divRef.current.contains(sourceElement)) {
      sourceElement.remove();
    }
    dragSourceRef.current = null;

    const tokenElement = createTokenElement(tokenData.label, tokenData.token);

    // Find insertion position
    let insertPosition = getCaretPositionFromPoint(e.clientX, e.clientY, divRef.current);
    if (!insertPosition) {
      insertPosition = getFallbackInsertPosition(divRef.current);
    }

    // Insert the token
    insertPosition.deleteContents();
    insertPosition.insertNode(tokenElement);

    normalizeTokenCaretBoundaries(divRef.current);
    placeCaretAdjacentToToken(tokenElement, 'after');

    // Trigger change event
    const htmlContent = divRef.current.innerHTML || '';
    setLocalValue(htmlContent);
    debouncedOnChange(htmlContent);
  }, [disabled, acceptsTokens, debouncedOnChange]);

  return (
    <div className="contenteditable-wrapper" ref={wrapperRef}>
      <div
        ref={divRef}
        contentEditable={!disabled}
        onInput={handleInput}
        onFocus={handleFocus}
        onBlur={handleBlur}
        onKeyDown={handleKeyDown}
        onPaste={handlePaste}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        className={`contenteditable-field ${className} ${disabled ? 'disabled' : ''} ${multiline ? 'multiline' : 'singleline'} ${isDragOver ? 'drag-over' : ''}`}
        data-placeholder={placeholder}
        role="textbox"
        aria-multiline={multiline}
        aria-labelledby={ariaLabelledBy}
        aria-label={ariaLabelledBy ? undefined : (placeholder || t('Text input'))}
        suppressContentEditableWarning={true}
      />
      {dropCursor && (
        <div
          className="drop-cursor"
          style={{
            left: `${dropCursor.x}px`,
            top: `${dropCursor.y}px`,
            height: `${dropCursor.height}px`,
          }}
        />
      )}
      {editIconTarget && !editingToken && !disabled && (
        <button
          className="token-edit-icon"
          style={{
            left: `${editIconTarget.x}px`,
            top: `${editIconTarget.y}px`,
          }}
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            openTokenEdit(editIconTarget.element);
          }}
          onMouseDown={(e) => e.preventDefault()}
          title={t('Edit token (Ctrl+E)')}
          aria-label={t('Edit token')}
          type="button"
        >
          <FiEdit2 size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
        </button>
      )}
      {editingToken && (
        <div
          className={`token-edit-popup ${editingToken.position === 'below' ? 'token-edit-popup-below' : ''}`}
          style={{
            left: `${editingToken.x}px`,
            top: `${editingToken.y}px`,
          }}
          onMouseDown={(e) => e.stopPropagation()}
        >
          <input
            ref={editInputRef}
            type="text"
            className="token-edit-input"
            defaultValue={editingToken.token}
            onKeyDown={handleEditInputKeyDown}
            aria-label={t('Edit token value')}
          />
          <div className="token-edit-actions">
            <button
              className="token-edit-save"
              onClick={saveTokenEdit}
              type="button"
            >
              {t('Save')}
            </button>
            <button
              className="token-edit-cancel"
              onClick={cancelTokenEdit}
              type="button"
            >
              {t('Cancel')}
            </button>
          </div>
        </div>
      )}
      {tokenPicker && acceptsTokens && !disabled && typeof document !== 'undefined' &&
        createPortal(
          // MODAL: a transparent backdrop blocks (and dismisses on) interaction
          // with everything behind the picker, and the picker itself is rendered
          // OUTSIDE the field/panel subtree so the background re-rendering /
          // unmounting can never unmount the picker. The open STATE still lives
          // in this component (`tokenPicker`); only the rendered DOM is portaled.
          //
          // The portal target is the `.modeler` ROOT (not document.body): it is
          // the scope where the `--modeler-*` theme variables and the
          // `.dark-mode` class are defined, so the picker is fully themed in both
          // light and dark — while still escaping the field's subtree. A high
          // z-index (>9999) lifts it above the app root and the popup/dialog
          // overlays. Falls back to document.body in isolated/test contexts.
          <>
            <div
              className="token-picker-backdrop"
              // A mousedown anywhere on the backdrop = click-outside → close,
              // restoring focus + caret to the field (DECISION B).
              onMouseDown={() => closeTokenPicker(true)}
            />
            <TokenPicker
              position={{ x: tokenPicker.x, y: tokenPicker.y }}
              onSelect={handleTokenPickerSelect}
              // Escape / × route here → user dismiss, restore field caret.
              onClose={() => closeTokenPicker(true)}
            />
          </>,
          document.querySelector('.modeler') ?? document.body,
        )}
    </div>
  );
};

export default ContentEditableField;
