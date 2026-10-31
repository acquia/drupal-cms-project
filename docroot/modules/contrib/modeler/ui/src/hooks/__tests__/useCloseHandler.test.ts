import { renderHook, act } from '@testing-library/react';
import { useCloseHandler } from '../useCloseHandler';

describe('useCloseHandler', () => {
  let mockShowConfirmationDialog: jest.Mock;
  let mockWrapper: HTMLDivElement;
  let originalLocation: Location;

  beforeEach(() => {
    jest.clearAllMocks();
    mockShowConfirmationDialog = jest.fn();

    // Create mock wrapper element
    mockWrapper = document.createElement('div');
    mockWrapper.id = 'workflow-modeler-wrapper';
    document.body.appendChild(mockWrapper);

    // Mock window.location
    originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { href: '' },
      writable: true,
      configurable: true,
    });
  });

  afterEach(() => {
    // Clean up DOM
    const wrapper = document.getElementById('workflow-modeler-wrapper');
    if (wrapper) wrapper.remove();

    Object.defineProperty(window, 'location', {
      value: originalLocation,
      writable: true,
      configurable: true,
    });
  });

  const renderUseCloseHandler = (props = {}) => {
    return renderHook(() =>
      useCloseHandler({
        settings: {},
        hasUnsavedChanges: false,
        showConfirmationDialog: mockShowConfirmationDialog,
        ...props,
      })
    );
  };

  describe('return values', () => {
    it('should return all required handlers and refs', () => {
      const { result } = renderUseCloseHandler();

      expect(typeof result.current.handleClose).toBe('function');
      expect(typeof result.current.handleSaveComplete).toBe('function');
      expect(result.current.saveButtonRef).toBeDefined();
    });
  });

  describe('handleClose', () => {
    it('should close immediately when no unsaved changes', () => {
      const { result } = renderUseCloseHandler({
        settings: { modeler: { stayInContextOnClose: true } },
        hasUnsavedChanges: false,
      });

      act(() => {
        result.current.handleClose();
      });

      expect(mockShowConfirmationDialog).not.toHaveBeenCalled();
      expect(mockWrapper.style.display).toBe('none');
    });

    it('should show confirmation dialog when has unsaved changes', () => {
      const { result } = renderUseCloseHandler({
        hasUnsavedChanges: true,
      });

      act(() => {
        result.current.handleClose();
      });

      expect(mockShowConfirmationDialog).toHaveBeenCalledWith(
        'Unsaved Changes',
        'You have unsaved changes. What would you like to do?',
        'warning',
        expect.any(Function),
        expect.any(Function)
      );
    });

    it('should navigate to collection URL when stayInContextOnClose is false', () => {
      const { result } = renderUseCloseHandler({
        settings: {
          modeler: { stayInContextOnClose: false },
          modeler_api: { collection_url: '/workflows' },
        },
        hasUnsavedChanges: false,
      });

      act(() => {
        result.current.handleClose();
      });

      expect(window.location.href).toBe('/workflows');
    });

    it('should hide wrapper when stayInContextOnClose is true', () => {
      const { result } = renderUseCloseHandler({
        settings: { modeler: { stayInContextOnClose: true } },
        hasUnsavedChanges: false,
      });

      act(() => {
        result.current.handleClose();
      });

      expect(mockWrapper.style.display).toBe('none');
    });
  });

  describe('handleSaveComplete', () => {
    it('should call setHasUnsavedChanges with false', () => {
      const { result } = renderUseCloseHandler();
      const mockSetHasUnsavedChanges = jest.fn();

      act(() => {
        result.current.handleSaveComplete(mockSetHasUnsavedChanges);
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(false);
    });

    it('should close after save when pending close flag is set', () => {
      const { result } = renderUseCloseHandler({
        settings: { modeler: { stayInContextOnClose: true } },
        hasUnsavedChanges: true,
      });

      // Trigger confirmation dialog
      act(() => {
        result.current.handleClose();
      });

      // Call the save and close callback (first callback)
      const saveAndCloseCallback = mockShowConfirmationDialog.mock.calls[0][3];

      // Mock the save button click
      const mockButton = document.createElement('button');
      (result.current.saveButtonRef as any).current = mockButton;

      act(() => {
        saveAndCloseCallback();
      });

      // Simulate save complete
      const mockSetHasUnsavedChanges = jest.fn();
      act(() => {
        result.current.handleSaveComplete(mockSetHasUnsavedChanges);
      });

      expect(mockWrapper.style.display).toBe('none');
    });

    it('should not close after save when no pending close', () => {
      const { result } = renderUseCloseHandler({
        settings: { modeler: { stayInContextOnClose: true } },
      });

      const mockSetHasUnsavedChanges = jest.fn();

      act(() => {
        result.current.handleSaveComplete(mockSetHasUnsavedChanges);
      });

      // Wrapper should still be visible
      expect(mockWrapper.style.display).toBe('');
    });
  });

  describe('confirmation dialog callbacks', () => {
    it('should provide save and close callback', () => {
      const { result } = renderUseCloseHandler({
        hasUnsavedChanges: true,
      });

      act(() => {
        result.current.handleClose();
      });

      const saveAndCloseCallback = mockShowConfirmationDialog.mock.calls[0][3];
      expect(typeof saveAndCloseCallback).toBe('function');
    });

    it('should provide close without save callback', () => {
      const { result } = renderUseCloseHandler({
        hasUnsavedChanges: true,
        settings: { modeler: { stayInContextOnClose: true } },
      });

      act(() => {
        result.current.handleClose();
      });

      const closeWithoutSaveCallback = mockShowConfirmationDialog.mock.calls[0][4];
      expect(typeof closeWithoutSaveCallback).toBe('function');

      // Execute the callback
      act(() => {
        closeWithoutSaveCallback();
      });

      expect(mockWrapper.style.display).toBe('none');
    });
  });

  describe('edge cases', () => {
    it('should handle missing wrapper element gracefully', () => {
      // Remove wrapper
      mockWrapper.remove();

      const { result } = renderUseCloseHandler({
        settings: { modeler: { stayInContextOnClose: true } },
        hasUnsavedChanges: false,
      });

      expect(() => {
        act(() => {
          result.current.handleClose();
        });
      }).not.toThrow();
    });

    it('should handle missing collection URL', () => {
      const { result } = renderUseCloseHandler({
        settings: { modeler: { stayInContextOnClose: false } },
        hasUnsavedChanges: false,
      });

      expect(() => {
        act(() => {
          result.current.handleClose();
        });
      }).not.toThrow();
    });
  });
});
