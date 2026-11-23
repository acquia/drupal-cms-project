import { renderHook, act } from '@testing-library/react';
import { useModalState } from '../useModalState';

// Mock the store
const mockSetModelData = jest.fn();

jest.mock('../../store/useModelStore', () => ({
  useModelStore: jest.fn((selector) => {
    const state = {
      setModelData: mockSetModelData,
    };
    return selector(state);
  }),
}));

describe('useModalState', () => {
  const mockSetHasUnsavedChanges = jest.fn();

  const defaultProps = {
    setHasUnsavedChanges: mockSetHasUnsavedChanges,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('initial state', () => {
    it('should initialize with metadata modal closed', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      expect(result.current.showMetadataModal).toBe(false);
    });

    it('should initialize with confirm dialog closed', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      expect(result.current.showConfirmDialog).toBe(false);
    });

    it('should initialize with empty confirm dialog title', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      expect(result.current.confirmDialogTitle).toBe('');
    });

    it('should initialize with empty confirm dialog message', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      expect(result.current.confirmDialogMessage).toBe('');
    });

    it('should initialize with info type for confirm dialog', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      expect(result.current.confirmDialogType).toBe('info');
    });

    it('should initialize with loading false', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      expect(result.current.confirmDialogLoading).toBe(false);
    });
  });

  describe('openMetadataModal', () => {
    it('should open metadata modal', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.openMetadataModal();
      });

      expect(result.current.showMetadataModal).toBe(true);
    });
  });

  describe('closeMetadataModal', () => {
    it('should close metadata modal', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.openMetadataModal();
        result.current.closeMetadataModal();
      });

      expect(result.current.showMetadataModal).toBe(false);
    });
  });

  describe('setShowMetadataModal', () => {
    it('should directly set metadata modal visibility to true', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.setShowMetadataModal(true);
      });

      expect(result.current.showMetadataModal).toBe(true);
    });

    it('should directly set metadata modal visibility to false', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.setShowMetadataModal(true);
        result.current.setShowMetadataModal(false);
      });

      expect(result.current.showMetadataModal).toBe(false);
    });
  });

  describe('onMetadataSubmit', () => {
    it('should update model data with new metadata', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      const metadata = {
        label: 'Test Model',
        version: '2.0.0',
        executable: true,
        template: false,
        storage: 'default',
        documentation: 'Some docs',
        tags: ['test', 'demo'],
        changelog: 'Initial version',
      };

      act(() => {
        result.current.onMetadataSubmit(metadata);
      });

      expect(mockSetModelData).toHaveBeenCalled();
      const updateFn = mockSetModelData.mock.calls[0][0];
      const updatedData = updateFn(null);
      expect(updatedData.metadata.label).toBe('Test Model');
      expect(updatedData.metadata.tags).toEqual(['test', 'demo']);
    });

    it('should preserve existing model data', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      const existingData = {
        id: 'existing-id',
        nodes: [{ id: 'node1' }],
        metadata: { label: 'Old Label' },
      };

      act(() => {
        result.current.onMetadataSubmit({
          label: 'New Label',
          tags: [],
        });
      });

      const updateFn = mockSetModelData.mock.calls[0][0];
      const updatedData = updateFn(existingData);
      expect(updatedData.id).toBe('existing-id');
      expect(updatedData.nodes).toEqual([{ id: 'node1' }]);
      expect(updatedData.metadata.label).toBe('New Label');
    });

    it('should close metadata modal after submit', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.openMetadataModal();
        result.current.onMetadataSubmit({ label: 'Test', tags: [] });
      });

      expect(result.current.showMetadataModal).toBe(false);
    });

    it('should mark changes as unsaved', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.onMetadataSubmit({ label: 'Test', tags: [] });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge recipe metadata the modal did carry', () => {
      const { result } = renderHook(() => useModalState(defaultProps));
      const configActions = [{ config: 'system.site', actions: { simple_config_update: { slogan: 'Hi' } } }];

      act(() => {
        result.current.onMetadataSubmit({
          label: 'Test Model',
          tags: [],
          summary: 'A one-line description.',
          recipes: ['core/recipes/article_tags'],
          config_actions: configActions,
          export_config: ['system.site'],
          modules: ['node', 'user'],
        });
      });

      const updateFn = mockSetModelData.mock.calls[0][0];
      const merged = updateFn({ metadata: { label: 'Old Label', summary: 'Stale.' } });
      expect(merged.metadata.summary).toBe('A one-line description.');
      expect(merged.metadata.recipes).toEqual(['core/recipes/article_tags']);
      expect(merged.metadata.config_actions).toEqual(configActions);
      expect(merged.metadata.export_config).toEqual(['system.site']);
      expect(merged.metadata.modules).toEqual(['node', 'user']);
    });
  });

  describe('showConfirmationDialog', () => {
    it('should show confirm dialog with title', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Test Title', 'Test Message');
      });

      expect(result.current.showConfirmDialog).toBe(true);
      expect(result.current.confirmDialogTitle).toBe('Test Title');
    });

    it('should show confirm dialog with message', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Test Message');
      });

      expect(result.current.confirmDialogMessage).toBe('Test Message');
    });

    it('should use default type when not provided', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
      });

      expect(result.current.confirmDialogType).toBe('info');
    });

    it('should set danger type', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message', 'danger');
      });

      expect(result.current.confirmDialogType).toBe('danger');
    });

    it('should set warning type', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message', 'warning');
      });

      expect(result.current.confirmDialogType).toBe('warning');
    });

    it('should reset loading state', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      // First set loading to true
      act(() => {
        result.current.setConfirmDialogLoading(true);
      });

      // Then open dialog which should reset loading
      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
      });

      expect(result.current.confirmDialogLoading).toBe(false);
    });
  });

  describe('handleConfirmDialog', () => {
    it('should execute callback when provided', () => {
      const mockCallback = jest.fn();
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message', 'info', mockCallback);
      });

      act(() => {
        result.current.handleConfirmDialog();
      });

      expect(mockCallback).toHaveBeenCalled();
    });

    it('should close dialog after confirmation', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
      });

      act(() => {
        result.current.handleConfirmDialog();
      });

      expect(result.current.showConfirmDialog).toBe(false);
    });

    it('should set loading state during callback execution', () => {
      const mockCallback = jest.fn();
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message', 'info', mockCallback);
      });

      // Note: Loading is set then immediately unset in same action
      act(() => {
        result.current.handleConfirmDialog();
      });

      expect(result.current.confirmDialogLoading).toBe(false);
    });

    it('should not throw when no callback provided', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
      });

      expect(() => {
        act(() => {
          result.current.handleConfirmDialog();
        });
      }).not.toThrow();
    });
  });

  describe('handleCancelDialog', () => {
    it('should close confirm dialog', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
      });

      act(() => {
        result.current.handleCancelDialog();
      });

      expect(result.current.showConfirmDialog).toBe(false);
    });

    it('should reset loading state', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
        result.current.setConfirmDialogLoading(true);
      });

      act(() => {
        result.current.handleCancelDialog();
      });

      expect(result.current.confirmDialogLoading).toBe(false);
    });

    it('should not execute callback', () => {
      const mockCallback = jest.fn();
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message', 'info', mockCallback);
      });

      act(() => {
        result.current.handleCancelDialog();
      });

      expect(mockCallback).not.toHaveBeenCalled();
    });
  });

  describe('setShowConfirmDialog', () => {
    it('should directly set confirm dialog visibility', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.setShowConfirmDialog(true);
      });

      expect(result.current.showConfirmDialog).toBe(true);
    });
  });

  describe('showConfirmationDialog options', () => {
    it('should set custom button labels from options', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message', 'danger', undefined, undefined, {
          primaryLabel: 'Delete',
          secondaryLabel: false,
          cancelLabel: 'Cancel',
          primaryVariant: 'danger',
        });
      });

      expect(result.current.confirmDialogPrimaryLabel).toBe('Delete');
      expect(result.current.confirmDialogSecondaryLabel).toBe(false);
      expect(result.current.confirmDialogCancelLabel).toBe('Cancel');
      expect(result.current.confirmDialogPrimaryVariant).toBe('danger');
    });

    it('should use undefined labels when options not provided', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.showConfirmationDialog('Title', 'Message');
      });

      expect(result.current.confirmDialogPrimaryLabel).toBeUndefined();
      expect(result.current.confirmDialogSecondaryLabel).toBeUndefined();
      expect(result.current.confirmDialogCancelLabel).toBeUndefined();
      expect(result.current.confirmDialogPrimaryVariant).toBeUndefined();
    });
  });

  describe('setConfirmDialogLoading', () => {
    it('should set loading state to true', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.setConfirmDialogLoading(true);
      });

      expect(result.current.confirmDialogLoading).toBe(true);
    });

    it('should set loading state to false', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      act(() => {
        result.current.setConfirmDialogLoading(true);
        result.current.setConfirmDialogLoading(false);
      });

      expect(result.current.confirmDialogLoading).toBe(false);
    });
  });

  describe('return value structure', () => {
    it('should return all expected state properties', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      expect(result.current).toHaveProperty('showMetadataModal');
      expect(result.current).toHaveProperty('showConfirmDialog');
      expect(result.current).toHaveProperty('confirmDialogTitle');
      expect(result.current).toHaveProperty('confirmDialogMessage');
      expect(result.current).toHaveProperty('confirmDialogType');
      expect(result.current).toHaveProperty('confirmDialogLoading');
    });

    it('should return all expected action functions', () => {
      const { result } = renderHook(() => useModalState(defaultProps));

      expect(typeof result.current.onMetadataSubmit).toBe('function');
      expect(typeof result.current.showConfirmationDialog).toBe('function');
      expect(typeof result.current.handleConfirmDialog).toBe('function');
      expect(typeof result.current.handleCancelDialog).toBe('function');
      expect(typeof result.current.openMetadataModal).toBe('function');
      expect(typeof result.current.closeMetadataModal).toBe('function');
      expect(typeof result.current.setShowMetadataModal).toBe('function');
      expect(typeof result.current.setShowConfirmDialog).toBe('function');
      expect(typeof result.current.setConfirmDialogLoading).toBe('function');
    });
  });
});
