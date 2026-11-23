/**
 * svgExport — Pure SVG rendering utilities for canvas export
 *
 * Generates a standalone SVG string from the ReactFlow canvas DOM.
 * All functions are pure (no React hooks) and operate on DOM elements
 * or plain data structures.
 *
 * Extracted from useExport to keep the hook focused on orchestration
 * while this module handles the ~300 lines of SVG serialization.
 */

import { t } from './translation';
import { getComponentLabel } from './componentUtils';

// ---------------------------------------------------------------------------
// Low-level helpers
// ---------------------------------------------------------------------------

/**
 * Parse a CSS transform string for translate values.
 */
export function parseTranslate(style: string): { x: number; y: number } {
  const match = style.match(/translate\(\s*([-\d.]+)px\s*,\s*([-\d.]+)px\s*\)/);
  if (match) {
    return { x: parseFloat(match[1]), y: parseFloat(match[2]) };
  }
  return { x: 0, y: 0 };
}

/**
 * Resolve a CSS variable to its computed value.
 */
export function resolveVar(varName: string): string {
  const el = document.querySelector('.modeler');
  if (!el) return '';
  return getComputedStyle(el).getPropertyValue(varName).trim();
}

/**
 * Escape special XML characters for both text content and attribute values.
 *
 * Escaping the single quote (`'`) as well makes values safe when placed inside
 * single-quoted XML attributes; it is harmless in text content.
 */
export function escapeXml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

/** Split text into lines that fit within maxWidth (approximate). */
export function wrapText(text: string, maxWidth: number, fontSize: number): string[] {
  const charWidth = fontSize * 0.6;
  const maxChars = Math.max(1, Math.floor(maxWidth / charWidth));
  const words = text.split(/\s+/);
  const lines: string[] = [];
  let current = '';
  for (const word of words) {
    const test = current ? `${current} ${word}` : word;
    if (test.length > maxChars && current) {
      lines.push(current);
      current = word;
    } else {
      current = test;
    }
  }
  if (current) lines.push(current);
  return lines.length > 0 ? lines : [''];
}

// ---------------------------------------------------------------------------
// Node type colors — resolved once at export time.
// ---------------------------------------------------------------------------

export interface NodeColors {
  bgPrimary: string;
  bgSurface: string;
  borderLight: string;
  textPrimary: string;
  textSecondary: string;
  typeEvent: string;
  typeEventLight: string;
  typeEventText: string;
  typeCondition: string;
  typeGateway: string;
  typeGatewayLight: string;
  typeGatewayHeader: string;
  annotationBg: string;
  annotationBorder: string;
  annotationText: string;
  edgeLabel: string;
  interactive: string;
  edgeOrder: string;
  textOnDark: string;
}

export function resolveColors(): NodeColors {
  return {
    bgPrimary: resolveVar('--modeler-color-bg-primary') || '#ffffff',
    bgSurface: resolveVar('--modeler-color-bg-surface') || '#f9fafb',
    borderLight: resolveVar('--modeler-color-border-light') || '#e5e7eb',
    textPrimary: resolveVar('--modeler-color-text-primary') || '#374151',
    textSecondary: resolveVar('--modeler-color-text-secondary') || '#4b5563',
    typeEvent: resolveVar('--modeler-color-type-event') || '#ff9800',
    typeEventLight: resolveVar('--modeler-color-type-event-light') || '#fff3e0',
    typeEventText: resolveVar('--modeler-color-warning-darker') || '#b45309',
    typeCondition: resolveVar('--modeler-color-type-condition') || '#2196f3',
    typeGateway: resolveVar('--modeler-color-type-gateway') || '#9c27b0',
    typeGatewayLight: resolveVar('--modeler-color-type-gateway-light') || '#f3e5f5',
    typeGatewayHeader: resolveVar('--modeler-color-type-gateway-header') || '#7b1fa2',
    annotationBg: resolveVar('--modeler-color-warning-light') || '#fef3c7',
    annotationBorder: resolveVar('--modeler-color-warning') || '#f59e0b',
    annotationText: resolveVar('--modeler-color-warning-darkest') || '#92400e',
    edgeLabel: resolveVar('--modeler-color-edge-default') || '#8b8b8b',
    interactive: resolveVar('--modeler-color-interactive') || '#3b82f6',
    edgeOrder: resolveVar('--modeler-color-edge-order') || '#9ca3af',
    textOnDark: resolveVar('--modeler-color-text-on-dark') || '#f9fafb',
  };
}

// ---------------------------------------------------------------------------
// Edge markup cleanup
// ---------------------------------------------------------------------------

/**
 * Clean extracted edge SVG markup so it renders correctly as a standalone
 * file.  This handles several issues:
 *
 * 1. Resolve CSS custom properties (`var(--modeler-...)`) — standalone SVGs
 *    cannot access an external stylesheet.
 * 2. Add `fill:none` to edge-path `<path>` style attributes — the SVG spec
 *    defaults `fill` to black; in the browser ReactFlow sets `fill:none` via
 *    a CSS rule that is absent from the exported file.
 * 3. Remove invisible interaction paths (`.react-flow__edge-interaction`)
 *    which are thick transparent hit-areas only relevant in the browser.
 * 4. Strip interactive DOM attributes (`tabindex`, `role`, `aria-label`,
 *    `aria-describedby`, `data-testid`) that add noise to the export.
 */
export function cleanEdgeMarkup(markup: string): string {
  let result = markup;

  // 1. Resolve CSS variables.
  result = result.replace(
    /var\(\s*(--modeler-[^)]+)\s*\)/g,
    (_match, varName: string) => resolveVar(varName) || _match,
  );

  // 2. Add fill:none to path style attributes that don't already set fill.
  result = result.replace(
    /(<path\b[^>]*\bstyle=")((?:(?!fill)[^"])*")/g,
    (_m, before: string, rest: string) => `${before}fill: none; ${rest}`,
  );

  // 3. Remove interaction paths (invisible hit-area overlays).
  result = result.replace(
    /<path\b[^>]*class="[^"]*react-flow__edge-interaction[^"]*"[^>]*><\/path>/g,
    '',
  );

  // 4. Strip interactive DOM attributes.
  result = result.replace(
    /\s*(?:tabindex|role|aria-label|aria-describedby|data-testid)="[^"]*"/g,
    '',
  );

  return result;
}

// ---------------------------------------------------------------------------
// Render individual node types as native SVG <g> elements.
// ---------------------------------------------------------------------------

export interface NodeInfo {
  x: number;
  y: number;
  w: number;
  h: number;
  label: string;
  type: string;          // 'start' | 'element' | 'gateway' | 'subprocess'
  annotation: string;
  annotationsVisible: boolean;
}

/** Rounded rectangle node (action / start / subprocess / gateway). */
export function renderRectNode(
  n: NodeInfo,
  c: NodeColors,
): string {
  const isStart = n.type === 'start';
  const isSubprocess = n.type === 'subprocess';
  const isGateway = n.type === 'gateway';
  const borderColor = isStart
    ? c.typeEvent
    : isSubprocess
      ? c.typeCondition
      : isGateway
        ? c.typeGateway
        : c.borderLight;
  const headerBg = isStart
    ? c.typeEventLight
    : isGateway
      ? c.typeGatewayLight
      : c.bgSurface;
  const headerText = isStart
    ? c.typeEventText
    : isGateway
      ? c.typeGatewayHeader
      : c.textSecondary;
  const borderStyle = isSubprocess ? 'stroke-dasharray="6 4"' : '';
  const typeLabel = isStart
    ? getComponentLabel('start')
    : isSubprocess
      ? getComponentLabel('subprocess')
      : isGateway
        ? getComponentLabel('gateway')
        : getComponentLabel('element');

  const r = 8; // border-radius
  const headerH = 28;
  const bodyPadY = 10;
  const labelFs = 13;
  const headerFs = 10;

  // Body content lines
  const labelLines = wrapText(n.label, n.w - 24, labelFs);

  // Build body text elements
  const labelStartY = headerH + bodyPadY + labelFs;
  const bodyTextEls: string[] = [];

  labelLines.forEach((line, i) => {
    bodyTextEls.push(
      `<text x="${n.w / 2}" y="${labelStartY + i * (labelFs + 3)}" ` +
      `text-anchor="middle" fill="${c.textPrimary}" font-size="${labelFs}" ` +
      `font-weight="500">${escapeXml(line)}</text>`,
    );
  });

  // Annotation label above node
  let annotationEl = '';
  if (n.annotation && n.annotationsVisible) {
    annotationEl = [
      `<rect x="${n.w / 2 - 60}" y="-22" width="120" height="18" rx="3" `,
      `  fill="${c.annotationBg}" stroke="${c.annotationBorder}" stroke-width="1"/>`,
      `<text x="${n.w / 2}" y="-9" text-anchor="middle" fill="${c.annotationText}" `,
      `  font-size="10">${escapeXml(n.annotation.slice(0, 25))}${n.annotation.length > 25 ? '…' : ''}</text>`,
    ].join('\n');
  }

  return [
    `<g transform="translate(${n.x}, ${n.y})">`,
    annotationEl,
    `  <rect x="0" y="0" width="${n.w}" height="${n.h}" rx="${r}" ry="${r}"`,
    `    fill="${c.bgPrimary}" stroke="${borderColor}" stroke-width="2" ${borderStyle}/>`,
    // Header background (clipped to top rounded corners)
    `  <clipPath id="hdr-${n.x}-${n.y}">`,
    `    <rect x="0" y="0" width="${n.w}" height="${headerH}" rx="${r}" ry="${r}"/>`,
    `  </clipPath>`,
    `  <rect x="0" y="0" width="${n.w}" height="${headerH}" fill="${headerBg}"`,
    `    clip-path="url(#hdr-${n.x}-${n.y})"/>`,
    `  <line x1="0" y1="${headerH}" x2="${n.w}" y2="${headerH}" stroke="${c.borderLight}" stroke-width="1"/>`,
    // Header text
    `  <text x="12" y="${headerH / 2 + 4}" fill="${headerText}" font-size="${headerFs}"`,
    `    font-weight="600" text-transform="uppercase" letter-spacing="0.5">${typeLabel.toUpperCase()}</text>`,
    // Body text
    ...bodyTextEls,
    `</g>`,
  ].join('\n');
}

/**
 * @deprecated Gateway nodes are now rendered as rectangular cards (same as
 * all other node types).  Kept temporarily for reference; will be removed.
 */

// ---------------------------------------------------------------------------
// Render edge labels (conditions, annotations, order badges) as SVG.
// ---------------------------------------------------------------------------

export interface EdgeLabelInfo {
  x: number;
  y: number;
  label: string;
  annotation: string;
  annotationsVisible: boolean;
  order: number | null;
  totalEdges: number;
}

export function renderEdgeLabel(info: EdgeLabelInfo, c: NodeColors): string {
  const parts: string[] = [];

  // Condition label
  if (info.label) {
    const tw = Math.min(info.label.length * 7 + 16, 200);
    const th = 22;
    parts.push(
      `<rect x="${info.x - tw / 2}" y="${info.y - th / 2}" width="${tw}" height="${th}" rx="4"`,
      `  fill="${c.bgPrimary}" stroke="${c.borderLight}" stroke-width="1"/>`,
      `<text x="${info.x}" y="${info.y + 4}" text-anchor="middle" fill="${c.textPrimary}"`,
      `  font-size="11">${escapeXml(info.label)}</text>`,
    );
  }

  // Annotation label above condition
  if (info.annotation && info.annotationsVisible) {
    const ay = info.label ? info.y - 24 : info.y;
    parts.push(
      `<rect x="${info.x - 55}" y="${ay - 9}" width="110" height="16" rx="3"`,
      `  fill="${c.annotationBg}" stroke="${c.annotationBorder}" stroke-width="1"/>`,
      `<text x="${info.x}" y="${ay + 2}" text-anchor="middle" fill="${c.annotationText}"`,
      `  font-size="9">${escapeXml(info.annotation.slice(0, 25))}${info.annotation.length > 25 ? '…' : ''}</text>`,
    );
  }

  // Order badge (pill-shaped with "Flow N" label)
  if (info.order !== null && info.totalEdges > 1) {
    const badgeLabel = `Flow ${info.order}`;
    const bx = info.x;
    const by = info.label ? info.y - 46 : info.y - 16;
    const pillWidth = badgeLabel.length * 6 + 12;
    const pillHeight = 16;
    parts.push(
      `<rect x="${bx - pillWidth / 2}" y="${by - pillHeight / 2}" width="${pillWidth}" height="${pillHeight}"`,
      `  rx="${pillHeight / 2}" ry="${pillHeight / 2}" fill="${c.edgeOrder}" stroke="${c.bgPrimary}" stroke-width="1"/>`,
      `<text x="${bx}" y="${by + 3}" text-anchor="middle" fill="${c.textOnDark}"`,
      `  font-size="9" font-weight="bold">${badgeLabel}</text>`,
    );
  }

  return parts.join('\n');
}

// ---------------------------------------------------------------------------
// Main export function — pure native SVG, no foreignObject.
// ---------------------------------------------------------------------------

/**
 * Generate an SVG string from the current ReactFlow canvas.
 *
 * Strategy: read node positions/sizes and edge paths from the live DOM,
 * then re-render everything as native SVG elements (rect, text, path,
 * circle).  This avoids foreignObject which only works in browsers.
 */
export function exportCanvasToSvg(): string {
  const viewport = document.querySelector('.react-flow__viewport') as HTMLElement | null;
  if (!viewport) {
    throw new Error(t('Canvas viewport not found'));
  }

  const colors = resolveColors();

  // ---------------------------------------------------------------------------
  // 1.  Read node info from the DOM.
  // ---------------------------------------------------------------------------
  const nodeEls = viewport.querySelectorAll<HTMLElement>('.react-flow__node');
  const visibleNodeEls = Array.from(nodeEls).filter(
    n => !n.style.display || n.style.display !== 'none',
  );

  if (visibleNodeEls.length === 0) {
    throw new Error(t('No visible elements to export'));
  }

  // Check if annotations are currently visible
  const annotationsVisible = !!viewport.querySelector('.annotation-label');

  const nodeInfos: NodeInfo[] = visibleNodeEls.map(el => {
    const style = el.style.cssText || el.getAttribute('style') || '';
    const { x, y } = parseTranslate(style);
    const w = el.offsetWidth || 200;
    const h = el.offsetHeight || 80;
    const label = el.querySelector('.node-label')?.textContent?.trim() || '';
    const annotation = el.querySelector('.annotation-icon')?.getAttribute('title') || '';

    let type = 'element';
    if (el.classList.contains('react-flow__node-start')) type = 'start';
    else if (el.classList.contains('react-flow__node-gateway')) type = 'gateway';
    else if (el.querySelector('.subprocess-node')) type = 'subprocess';

    return { x, y, w, h, label, type, annotation, annotationsVisible };
  });

  // ---------------------------------------------------------------------------
  // 2.  Calculate bounds.
  // ---------------------------------------------------------------------------
  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;

  nodeInfos.forEach(n => {
    minX = Math.min(minX, n.x);
    minY = Math.min(minY, n.y - (n.annotation && n.annotationsVisible ? 30 : 0));
    maxX = Math.max(maxX, n.x + n.w);
    maxY = Math.max(maxY, n.y + n.h);
  });

  const padding = 40;
  const vbX = minX - padding;
  const vbY = minY - padding;
  const vbW = maxX - minX + padding * 2;
  const vbH = maxY - minY + padding * 2;

  // ---------------------------------------------------------------------------
  // 3.  Extract native SVG edges from the DOM (already valid SVG).
  // ---------------------------------------------------------------------------
  const edgesSvgEls = viewport.querySelectorAll('.react-flow__edges');
  const edgesMarkupParts: string[] = [];
  edgesSvgEls.forEach(el => {
    // We need the inner content (<defs> + <g>s), not the wrapping <svg>
    // so we can place them directly in our output SVG.
    // Clean edge markup for standalone SVG rendering.
    edgesMarkupParts.push(cleanEdgeMarkup(el.innerHTML));
  });

  // ---------------------------------------------------------------------------
  // 4.  Extract edge labels from the DOM.
  // ---------------------------------------------------------------------------
  const edgeLabelEls = viewport.querySelectorAll<HTMLElement>('.edge-label-container');
  const edgeLabelInfos: EdgeLabelInfo[] = Array.from(edgeLabelEls).map(el => {
    const style = el.getAttribute('style') || '';
    // Parse the double-translate: translate(-50%, -50%) translate(Xpx, Ypx)
    const matches = style.match(/translate\(\s*([-\d.]+)px\s*,\s*([-\d.]+)px\s*\)/g);
    let lx = 0;
    let ly = 0;
    if (matches && matches.length >= 2) {
      const m = matches[1].match(/([-\d.]+)px\s*,\s*([-\d.]+)px/);
      if (m) { lx = parseFloat(m[1]); ly = parseFloat(m[2]); }
    } else if (matches && matches.length === 1) {
      const m = matches[0].match(/([-\d.]+)px\s*,\s*([-\d.]+)px/);
      if (m) { lx = parseFloat(m[1]); ly = parseFloat(m[2]); }
    }

    const label = el.querySelector('.condition-edge-label span')?.textContent?.trim() || '';
    const annotation = el.querySelector('.annotation-icon')?.getAttribute('title') || '';

    return { x: lx, y: ly, label, annotation, annotationsVisible, order: null, totalEdges: 0 };
  });

  // Edge order badges
  const orderEls = viewport.querySelectorAll<HTMLElement>('.edge-order-number');
  const orderInfos: EdgeLabelInfo[] = Array.from(orderEls).map(el => {
    const style = el.getAttribute('style') || '';
    const matches = style.match(/translate\(\s*([-\d.]+)px\s*,\s*([-\d.]+)px\s*\)/g);
    let ox = 0;
    let oy = 0;
    if (matches && matches.length >= 2) {
      const m = matches[1].match(/([-\d.]+)px\s*,\s*([-\d.]+)px/);
      if (m) { ox = parseFloat(m[1]); oy = parseFloat(m[2]); }
    } else if (matches && matches.length === 1) {
      const m = matches[0].match(/([-\d.]+)px\s*,\s*([-\d.]+)px/);
      if (m) { ox = parseFloat(m[1]); oy = parseFloat(m[2]); }
    }
    const badge = el.querySelector('.edge-order-badge');
    const badgeText = badge?.textContent?.trim() || '';
    const orderMatch = badgeText.match(/(\d+)/);
    const order = orderMatch ? parseInt(orderMatch[1], 10) : null;
    return {
      x: ox, y: oy, label: '', annotation: '', annotationsVisible: false,
      order, totalEdges: 2,
    };
  });

  // ---------------------------------------------------------------------------
  // 5.  Build the SVG.
  // ---------------------------------------------------------------------------
  const nodeSvgParts = nodeInfos.map(n => renderRectNode(n, colors));

  const edgeLabelParts = [
    ...edgeLabelInfos.map(info => renderEdgeLabel(info, colors)),
    ...orderInfos.map(info => renderEdgeLabel(info, colors)),
  ];

  const fontFamily = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

  return [
    `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"`,
    `  width="${Math.ceil(vbW)}" height="${Math.ceil(vbH)}"`,
    `  viewBox="${vbX} ${vbY} ${vbW} ${vbH}"`,
    `  font-family="${fontFamily}">`,
    // Edge paths (native SVG from ReactFlow)
    `  <g class="edges">`,
    ...edgesMarkupParts,
    `  </g>`,
    // Edge labels
    `  <g class="edge-labels">`,
    ...edgeLabelParts,
    `  </g>`,
    // Nodes
    `  <g class="nodes">`,
    ...nodeSvgParts,
    `  </g>`,
    `</svg>`,
  ].join('\n');
}
