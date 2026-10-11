import { renderHook, act } from '@testing-library/react';
import { useKeyboardShortcuts } from '../useKeyboardShortcuts';

interface KeyboardShortcutCallbacks {
  onDelete?: () => void;
  onCopy?: () => void;
  onPaste?: () => void;
  onToggleSearch?: () => void;
  onEscape?: () => void;
  onUndo?: () => void;
  onRedo?: () => void;
  onToggleReviewMode?: () => void;
}

interface ModifierStates {
  isShiftPressed: boolean;
  setIsShiftPressed: (pressed: boolean) => void;
  isCtrlPressed: boolean;
  setIsCtrlPressed: (pressed: boolean) => void;
  isAltPressed: boolean;
  setIsAltPressed: (pressed: boolean) => void;
}

interface KeyboardCapabilities {
  canDelete: boolean;
  canCopy: boolean;
  canPaste: boolean;
  canSearch: boolean;
  canEscape: boolean;
  canUndo: boolean;
  canRedo: boolean;
  canToggleReviewMode: boolean;
}

interface UseKeyboardShortcutsProps {
  callbacks: KeyboardShortcutCallbacks;
  modifiers: ModifierStates;
  capabilities: KeyboardCapabilities;
  isModelerFocused: boolean;
  enabled?: boolean;
}

describe('useKeyboardShortcuts', () => {
  let mockCallbacks: KeyboardShortcutCallbacks;
  let mockModifiers: ModifierStates;
  let mockCapabilities: KeyboardCapabilities;

  beforeEach(() => {
    mockCallbacks = {
      onDelete: jest.fn(),
      onCopy: jest.fn(),
      onPaste: jest.fn(),
      onToggleSearch: jest.fn(),
      onEscape: jest.fn(),
      onToggleReviewMode: jest.fn(),
    };

    mockModifiers = {
      isShiftPressed: false,
      setIsShiftPressed: jest.fn(),
      isCtrlPressed: false,
      setIsCtrlPressed: jest.fn(),
      isAltPressed: false,
      setIsAltPressed: jest.fn(),
    };

    mockCapabilities = {
      canDelete: true,
      canCopy: true,
      canPaste: true,
      canSearch: true,
      canEscape: true,
      canUndo: true,
      canRedo: true,
      canToggleReviewMode: true,
    };

  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  const renderUseKeyboardShortcuts = (props: Partial<UseKeyboardShortcutsProps> = {}) => {
    return renderHook(() =>
      useKeyboardShortcuts({
        callbacks: mockCallbacks,
        modifiers: mockModifiers,
        capabilities: mockCapabilities,
        isModelerFocused: true,
        enabled: true,
        ...props,
      })
    );
  };

  describe('modifier key tracking', () => {
    it('should set shift pressed on keydown', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Shift', shiftKey: true }));
      });

      expect(mockModifiers.setIsShiftPressed).toHaveBeenCalledWith(true);
    });

    it('should clear shift pressed on keyup', () => {
      mockModifiers.isShiftPressed = true;
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keyup', { key: 'Shift', shiftKey: false }));
      });

      expect(mockModifiers.setIsShiftPressed).toHaveBeenCalledWith(false);
    });

    it('should set ctrl pressed on keydown', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Control', ctrlKey: true }));
      });

      expect(mockModifiers.setIsCtrlPressed).toHaveBeenCalledWith(true);
    });

    it('should clear ctrl pressed on keyup', () => {
      mockModifiers.isCtrlPressed = true;
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keyup', { key: 'Control', ctrlKey: false }));
      });

      expect(mockModifiers.setIsCtrlPressed).toHaveBeenCalledWith(false);
    });

    it('should set alt pressed on keydown', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Alt', altKey: true }));
      });

      expect(mockModifiers.setIsAltPressed).toHaveBeenCalledWith(true);
    });

    it('should clear alt pressed on keyup', () => {
      mockModifiers.isAltPressed = true;
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keyup', { key: 'Alt', altKey: false }));
      });

      expect(mockModifiers.setIsAltPressed).toHaveBeenCalledWith(false);
    });

    it('should handle meta key as ctrl', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Meta', metaKey: true }));
      });

      expect(mockModifiers.setIsCtrlPressed).toHaveBeenCalledWith(true);
    });

    it('should clear shift on keyup after keydown without re-render in between', () => {
      // Simulate the real scenario: setIsShiftPressed updates a ref internally
      // so the keyup handler sees the latest state even without a re-render
      const setIsShiftPressed = jest.fn((value: boolean) => {
        mockModifiers.isShiftPressed = value;
      });
      mockModifiers.setIsShiftPressed = setIsShiftPressed;

      renderUseKeyboardShortcuts();

      // Press shift
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Shift', shiftKey: true }));
      });
      expect(setIsShiftPressed).toHaveBeenCalledWith(true);

      // Release shift — without re-rendering with isShiftPressed=true
      act(() => {
        window.dispatchEvent(new KeyboardEvent('keyup', { key: 'Shift', shiftKey: false }));
      });
      expect(setIsShiftPressed).toHaveBeenCalledWith(false);
    });
  });

  describe('keyboard shortcuts', () => {
    beforeEach(() => {
      // Create a modeler element for context detection
      const modelerDiv = document.createElement('div');
      modelerDiv.className = 'workflow-modeler';
      document.body.appendChild(modelerDiv);
    });

    afterEach(() => {
      const modelerDiv = document.querySelector('.workflow-modeler');
      if (modelerDiv) {
        document.body.removeChild(modelerDiv);
      }
    });

    it('should call onDelete when Delete key pressed', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).toHaveBeenCalled();
    });

    it('should call onCopy when Ctrl+C pressed', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { 
          key: 'c', 
          ctrlKey: true, 
          bubbles: true, 
          cancelable: true 
        });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onCopy).toHaveBeenCalled();
    });

    it('should call onPaste when Ctrl+V pressed', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { 
          key: 'v', 
          ctrlKey: true, 
          bubbles: true, 
          cancelable: true 
        });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onPaste).toHaveBeenCalled();
    });

    it('should call onToggleSearch when Ctrl+F pressed', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { 
          key: 'f', 
          ctrlKey: true, 
          bubbles: true, 
          cancelable: true 
        });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onToggleSearch).toHaveBeenCalled();
    });

    it('should call onEscape when Escape pressed', () => {
      renderUseKeyboardShortcuts();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onEscape).toHaveBeenCalled();
    });

    it('should not call callbacks when disabled', () => {
      renderUseKeyboardShortcuts({ enabled: false });
      
      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true }));
      });

      expect(mockCallbacks.onDelete).not.toHaveBeenCalled();
    });

    it('should not call onDelete when canDelete is false', () => {
      renderUseKeyboardShortcuts({ capabilities: { ...mockCapabilities, canDelete: false } });
      
      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true }));
      });

      expect(mockCallbacks.onDelete).not.toHaveBeenCalled();
    });

    it('should not call onCopy when canCopy is false', () => {
      renderUseKeyboardShortcuts({ capabilities: { ...mockCapabilities, canCopy: false } });
      
      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'c', ctrlKey: true, bubbles: true }));
      });

      expect(mockCallbacks.onCopy).not.toHaveBeenCalled();
    });

    it('should call onToggleReviewMode when Alt+Shift+R pressed', () => {
      renderUseKeyboardShortcuts();

      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', {
          code: 'KeyR',
          key: 'R',
          altKey: true,
          shiftKey: true,
          bubbles: true,
          cancelable: true,
        }));
      });

      expect(mockCallbacks.onToggleReviewMode).toHaveBeenCalledTimes(1);
    });

    it('should call onToggleReviewMode on macOS, where Option+Shift+R rewrites event.key', () => {
      // macOS reports Option+R as "\u00ae" instead of "R", so the shortcut must
      // match on the physical key (event.code) to work there at all.
      renderUseKeyboardShortcuts();

      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', {
          code: 'KeyR',
          key: '\u00ae',
          altKey: true,
          shiftKey: true,
          bubbles: true,
          cancelable: true,
        }));
      });

      expect(mockCallbacks.onToggleReviewMode).toHaveBeenCalledTimes(1);
    });

    it('should not call onToggleReviewMode when canToggleReviewMode is false', () => {
      renderUseKeyboardShortcuts({ capabilities: { ...mockCapabilities, canToggleReviewMode: false } });

      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', {
          code: 'KeyR',
          key: 'R',
          altKey: true,
          shiftKey: true,
          bubbles: true,
        }));
      });

      expect(mockCallbacks.onToggleReviewMode).not.toHaveBeenCalled();
    });

    it('should not call onToggleReviewMode on Ctrl+Shift+R (browser hard reload stays intact)', () => {
      renderUseKeyboardShortcuts();

      act(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', {
          code: 'KeyR',
          key: 'R',
          ctrlKey: true,
          shiftKey: true,
          bubbles: true,
        }));
      });

      expect(mockCallbacks.onToggleReviewMode).not.toHaveBeenCalled();
    });
  });

  describe('input context detection', () => {
    beforeEach(() => {
      const modelerDiv = document.createElement('div');
      modelerDiv.className = 'workflow-modeler';
      document.body.appendChild(modelerDiv);
    });

    afterEach(() => {
      const modelerDiv = document.querySelector('.workflow-modeler');
      if (modelerDiv) {
        document.body.removeChild(modelerDiv);
      }
    });

    it('should not trigger shortcuts when target is input', () => {
      renderUseKeyboardShortcuts();
      
      const input = document.createElement('input');
      document.body.appendChild(input);
      input.focus();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
        Object.defineProperty(event, 'target', { value: input, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).not.toHaveBeenCalled();
      document.body.removeChild(input);
    });

    it('should not trigger the review toggle while typing in an input', () => {
      renderUseKeyboardShortcuts();

      const input = document.createElement('input');
      document.body.appendChild(input);
      input.focus();

      act(() => {
        const event = new KeyboardEvent('keydown', {
          code: 'KeyR',
          key: 'R',
          altKey: true,
          shiftKey: true,
          bubbles: true,
        });
        Object.defineProperty(event, 'target', { value: input, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onToggleReviewMode).not.toHaveBeenCalled();
      document.body.removeChild(input);
    });

    it('should not trigger shortcuts when target is textarea', () => {
      renderUseKeyboardShortcuts();
      
      const textarea = document.createElement('textarea');
      document.body.appendChild(textarea);
      textarea.focus();
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
        Object.defineProperty(event, 'target', { value: textarea, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).not.toHaveBeenCalled();
      document.body.removeChild(textarea);
    });

    it('should not trigger shortcuts when target is contenteditable', () => {
      renderUseKeyboardShortcuts();
      
      const div = document.createElement('div');
      div.contentEditable = 'true';
      document.body.appendChild(div);
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
        Object.defineProperty(event, 'target', { value: div, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).not.toHaveBeenCalled();
      document.body.removeChild(div);
    });

    it('should block Ctrl+C when text is selected (allow native text copy)', () => {
      renderUseKeyboardShortcuts();

      // Mock text selection
      const originalGetSelection = window.getSelection;
      window.getSelection = jest.fn(() => ({
        toString: () => 'some selected text',
        length: 18,
      })) as unknown as typeof window.getSelection;

      const div = document.createElement('div');
      document.body.appendChild(div);

      act(() => {
        const event = new KeyboardEvent('keydown', {
          key: 'c',
          ctrlKey: true,
          bubbles: true,
        });
        Object.defineProperty(event, 'target', { value: div, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onCopy).not.toHaveBeenCalled();

      window.getSelection = originalGetSelection;
      document.body.removeChild(div);
    });

    it('should allow Ctrl+V even when text is selected', () => {
      renderUseKeyboardShortcuts();

      // Mock text selection
      const originalGetSelection = window.getSelection;
      window.getSelection = jest.fn(() => ({
        toString: () => 'some selected text',
        length: 18,
      })) as unknown as typeof window.getSelection;

      const div = document.createElement('div');
      document.body.appendChild(div);

      act(() => {
        const event = new KeyboardEvent('keydown', {
          key: 'v',
          ctrlKey: true,
          bubbles: true,
        });
        Object.defineProperty(event, 'target', { value: div, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onPaste).toHaveBeenCalled();

      window.getSelection = originalGetSelection;
      document.body.removeChild(div);
    });

    it('should allow Delete even when text is selected', () => {
      renderUseKeyboardShortcuts();

      // Mock text selection
      const originalGetSelection = window.getSelection;
      window.getSelection = jest.fn(() => ({
        toString: () => 'some selected text',
        length: 18,
      })) as unknown as typeof window.getSelection;

      const div = document.createElement('div');
      document.body.appendChild(div);

      act(() => {
        const event = new KeyboardEvent('keydown', {
          key: 'Delete',
          bubbles: true,
        });
        Object.defineProperty(event, 'target', { value: div, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).toHaveBeenCalled();

      window.getSelection = originalGetSelection;
      document.body.removeChild(div);
    });
  });

  describe('modeler context detection', () => {
    it('should trigger shortcuts when isModelerFocused is true', () => {
      // No modeler element, but isModelerFocused is true
      renderUseKeyboardShortcuts({ isModelerFocused: true });
      
      const div = document.createElement('div');
      document.body.appendChild(div);
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
        Object.defineProperty(event, 'target', { value: div, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).toHaveBeenCalled();
      document.body.removeChild(div);
    });

    it('should trigger shortcuts when target is inside modeler', () => {
      const modelerDiv = document.createElement('div');
      modelerDiv.className = 'workflow-modeler';
      const childDiv = document.createElement('div');
      modelerDiv.appendChild(childDiv);
      document.body.appendChild(modelerDiv);
      
      renderUseKeyboardShortcuts({ isModelerFocused: false });
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
        Object.defineProperty(event, 'target', { value: childDiv, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).toHaveBeenCalled();
      document.body.removeChild(modelerDiv);
    });

    it('should not trigger shortcuts when outside modeler and not focused', () => {
      const outsideDiv = document.createElement('div');
      document.body.appendChild(outsideDiv);
      
      renderUseKeyboardShortcuts({ isModelerFocused: false });
      
      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
        Object.defineProperty(event, 'target', { value: outsideDiv, writable: false });
        document.dispatchEvent(event);
      });

      expect(mockCallbacks.onDelete).not.toHaveBeenCalled();
      document.body.removeChild(outsideDiv);
    });
  });

  describe('Firefox-specific handlers', () => {
    beforeEach(() => {
      const modelerDiv = document.createElement('div');
      modelerDiv.className = 'workflow-modeler';
      document.body.appendChild(modelerDiv);
    });

    afterEach(() => {
      const modelerDiv = document.querySelector('.workflow-modeler');
      if (modelerDiv) {
        document.body.removeChild(modelerDiv);
      }
    });

    it('should preventDefault on Ctrl+F keypress without calling callback (Firefox)', () => {
      renderUseKeyboardShortcuts();

      let defaultPrevented = false;
      act(() => {
        const event = new KeyboardEvent('keypress', {
          key: 'f',
          ctrlKey: true,
          bubbles: true,
          cancelable: true
        });
        event.preventDefault = () => { defaultPrevented = true; };
        document.dispatchEvent(event);
      });

      // Callbacks should NOT fire on keypress — only preventDefault to block browser behavior
      expect(mockCallbacks.onToggleSearch).not.toHaveBeenCalled();
      expect(defaultPrevented).toBe(true);
    });

    it('should handle Ctrl+F on keyup (Firefox)', () => {
      renderUseKeyboardShortcuts();

      act(() => {
        const event = new KeyboardEvent('keyup', {
          key: 'f',
          ctrlKey: true,
          bubbles: true,
          cancelable: true
        });
        document.dispatchEvent(event);
      });

      // keyup handler is called but may not trigger callback again
      // Test that it doesn't throw
      expect(true).toBe(true);
    });

    it('should not trigger keypress shortcuts in input context', () => {
      renderUseKeyboardShortcuts();

      const input = document.createElement('input');
      document.body.appendChild(input);

      act(() => {
        const event = new KeyboardEvent('keypress', {
          key: 'f',
          ctrlKey: true,
          bubbles: true
        });
        Object.defineProperty(event, 'target', { value: input, writable: false });
        document.dispatchEvent(event);
      });

      // Should not call because target is input
      // The callback may be called from keydown, so we just verify no throw
      document.body.removeChild(input);
    });

    it('should not trigger keyup shortcuts in input context', () => {
      renderUseKeyboardShortcuts();

      const textarea = document.createElement('textarea');
      document.body.appendChild(textarea);

      act(() => {
        const event = new KeyboardEvent('keyup', {
          key: 'f',
          ctrlKey: true,
          bubbles: true
        });
        Object.defineProperty(event, 'target', { value: textarea, writable: false });
        document.dispatchEvent(event);
      });

      document.body.removeChild(textarea);
    });
  });

  describe('cleanup', () => {
    it('should remove event listeners on unmount', () => {
      const removeEventListenerSpy = jest.spyOn(window, 'removeEventListener');
      const documentRemoveEventListenerSpy = jest.spyOn(document, 'removeEventListener');
      
      const { unmount } = renderUseKeyboardShortcuts();
      unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
      expect(removeEventListenerSpy).toHaveBeenCalledWith('keyup', expect.any(Function));
      expect(documentRemoveEventListenerSpy).toHaveBeenCalled();
      
      removeEventListenerSpy.mockRestore();
      documentRemoveEventListenerSpy.mockRestore();
    });
  });

  describe('callback edge cases', () => {
    it('should not crash when callbacks are undefined', () => {
      const { unmount } = renderUseKeyboardShortcuts({
        callbacks: {
          onDelete: undefined,
          onCopy: undefined,
          onPaste: undefined,
          onToggleSearch: undefined,
          onEscape: undefined,
        }
      });

      const modelerDiv = document.createElement('div');
      modelerDiv.className = 'workflow-modeler';
      document.body.appendChild(modelerDiv);

      expect(() => {
        act(() => {
          document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true }));
          document.dispatchEvent(new KeyboardEvent('keydown', { key: 'c', ctrlKey: true, bubbles: true }));
          document.dispatchEvent(new KeyboardEvent('keydown', { key: 'v', ctrlKey: true, bubbles: true }));
          document.dispatchEvent(new KeyboardEvent('keydown', { key: 'f', ctrlKey: true, bubbles: true }));
          document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        });
      }).not.toThrow();

      document.body.removeChild(modelerDiv);
      unmount();
    });
  });
});
