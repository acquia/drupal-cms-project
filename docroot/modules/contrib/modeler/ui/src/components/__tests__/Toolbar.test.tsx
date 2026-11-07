import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import Toolbar from '../Toolbar';

// Mock reactflow
jest.mock('reactflow', () => ({
  useReactFlow: () => ({
    zoomIn: jest.fn(),
    zoomOut: jest.fn(),
    fitView: jest.fn(),
    setViewport: jest.fn(),
    getNodes: () => [],
  }),
}));

// Mock SearchBar
jest.mock('../SearchBar', () => {
  const MockSearchBar = React.forwardRef<any, any>((_props, _ref) => (
    <div data-testid="search-bar">SearchBar</div>
  ));
  MockSearchBar.displayName = 'SearchBar';
  return MockSearchBar;
});

// Mock ToolbarMenu
jest.mock('../ToolbarMenu', () => {
  const MockToolbarMenu = (props: any) => (
    <div data-testid="toolbar-menu">
      <button onClick={props.onOpenMetadata} title="Model Settings">Settings</button>
    </div>
  );
  MockToolbarMenu.displayName = 'ToolbarMenu';
  return { __esModule: true, default: MockToolbarMenu };
});

// Mock modelUtils
jest.mock('../../utils/modelUtils', () => ({
  getFitViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
}));

// Mock store
jest.mock('../../store/useUISettingsStore', () => ({
  useUISettingsStore: jest.fn((selector) => {
    const state = {
      darkMode: false,
      toggleDarkMode: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

describe('Toolbar', () => {
  const defaultProps = {
    onSave: jest.fn(),
    onOpenMetadata: jest.fn(),
    onToggleSearch: jest.fn(),
    onToggleMessages: jest.fn(),
    onClearMessages: jest.fn(),
    isLocked: false,
    isSearchVisible: false,
    hasMessages: false,
    messagesVisible: false,
    modelName: 'Test Model',
    hasUnsavedChanges: false,
    onClose: jest.fn(),
    settings: {},
    drupal: undefined,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    // Mock jQuery for save button tests
    (window as any).jQuery = undefined;
  });

  describe('rendering', () => {
    it('should render toolbar container', () => {
      render(<Toolbar {...defaultProps} />);
      expect(document.querySelector('.workflow-toolbar')).toBeInTheDocument();
    });

    it('should render save button', () => {
      render(<Toolbar {...defaultProps} />);
      expect(screen.getByTitle('Save Model')).toBeInTheDocument();
    });

    it('should render model name', () => {
      render(<Toolbar {...defaultProps} modelName="My Model" />);
      expect(screen.getByText('My Model')).toBeInTheDocument();
    });

    it('should render toolbar menu', () => {
      render(<Toolbar {...defaultProps} />);
      expect(screen.getByTestId('toolbar-menu')).toBeInTheDocument();
    });

    it('should render close button', () => {
      render(<Toolbar {...defaultProps} />);
      expect(screen.getByTitle('Close Modeler')).toBeInTheDocument();
    });

    it('should render inline search bar', () => {
      render(<Toolbar {...defaultProps} />);
      expect(screen.getByTestId('search-bar')).toBeInTheDocument();
    });
  });

  describe('unsaved changes indicator', () => {
    it('should show unsaved indicator when hasUnsavedChanges is true', () => {
      render(<Toolbar {...defaultProps} hasUnsavedChanges={true} />);
      expect(document.querySelector('.unsaved-indicator')).toBeInTheDocument();
    });

    it('should not show unsaved indicator when hasUnsavedChanges is false', () => {
      render(<Toolbar {...defaultProps} hasUnsavedChanges={false} />);
      expect(document.querySelector('.unsaved-indicator')).not.toBeInTheDocument();
    });
  });

  describe('button click handlers', () => {
    it('should call onOpenMetadata via toolbar menu', () => {
      const onOpenMetadata = jest.fn();
      render(<Toolbar {...defaultProps} onOpenMetadata={onOpenMetadata} />);

      fireEvent.click(screen.getByTitle('Model Settings'));

      expect(onOpenMetadata).toHaveBeenCalled();
    });

    it('should call onClose when close clicked', () => {
      const onClose = jest.fn();
      render(<Toolbar {...defaultProps} onClose={onClose} />);

      fireEvent.click(screen.getByTitle('Close Modeler'));

      expect(onClose).toHaveBeenCalled();
    });
  });

  describe('messages toggle', () => {
    it('should not show messages toggle when hasMessages is false', () => {
      render(<Toolbar {...defaultProps} hasMessages={false} />);
      expect(screen.queryByTitle('Show messages')).not.toBeInTheDocument();
    });

    it('should show messages toggle when hasMessages is true', () => {
      render(<Toolbar {...defaultProps} hasMessages={true} />);
      expect(screen.getByTitle('Show messages')).toBeInTheDocument();
    });

    it('should call onToggleMessages when clicked', () => {
      const onToggleMessages = jest.fn();
      render(
        <Toolbar
          {...defaultProps}
          hasMessages={true}
          onToggleMessages={onToggleMessages}
        />
      );

      fireEvent.click(screen.getByTitle('Show messages'));

      expect(onToggleMessages).toHaveBeenCalled();
    });

    it('should show "Hide messages" tooltip when messages are visible', () => {
      render(
        <Toolbar
          {...defaultProps}
          hasMessages={true}
          messagesVisible={true}
        />
      );
      expect(screen.getByTitle('Hide messages')).toBeInTheDocument();
    });

    it('should show "Show messages" tooltip when messages are hidden', () => {
      render(
        <Toolbar
          {...defaultProps}
          hasMessages={true}
          messagesVisible={false}
        />
      );
      expect(screen.getByTitle('Show messages')).toBeInTheDocument();
    });

    it('should apply inactive class when messages are visible', () => {
      render(
        <Toolbar
          {...defaultProps}
          hasMessages={true}
          messagesVisible={true}
        />
      );
      const button = screen.getByTitle('Hide messages');
      expect(button.classList.contains('inactive')).toBe(true);
    });

    it('should apply active class when messages are hidden', () => {
      render(
        <Toolbar
          {...defaultProps}
          hasMessages={true}
          messagesVisible={false}
        />
      );
      const button = screen.getByTitle('Show messages');
      expect(button.classList.contains('active')).toBe(true);
    });

    it('should show clear button when hasMessages is true', () => {
      render(<Toolbar {...defaultProps} hasMessages={true} />);
      expect(screen.getByTitle('Clear messages')).toBeInTheDocument();
    });

    it('should not show clear button when hasMessages is false', () => {
      render(<Toolbar {...defaultProps} hasMessages={false} />);
      expect(screen.queryByTitle('Clear messages')).not.toBeInTheDocument();
    });

    it('should call onClearMessages when clear button is clicked', () => {
      const onClearMessages = jest.fn();
      render(
        <Toolbar
          {...defaultProps}
          hasMessages={true}
          onClearMessages={onClearMessages}
        />
      );

      fireEvent.click(screen.getByTitle('Clear messages'));

      expect(onClearMessages).toHaveBeenCalled();
    });
  });

  describe('save button', () => {
    it('should be disabled when hasUnsavedChanges is false', () => {
      render(<Toolbar {...defaultProps} hasUnsavedChanges={false} />);
      expect(screen.getByTitle('Save Model')).toBeDisabled();
    });

    it('should be enabled when hasUnsavedChanges is true', () => {
      render(<Toolbar {...defaultProps} hasUnsavedChanges={true} />);
      expect(screen.getByTitle('Save Model')).not.toBeDisabled();
    });

    it('should handle save click without drupal object', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      render(<Toolbar {...defaultProps} hasUnsavedChanges={true} />);

      fireEvent.click(screen.getByTitle('Save Model'));

      expect(consoleSpy).toHaveBeenCalledWith('Drupal object not available');
      consoleSpy.mockRestore();
    });

    it('should handle save click with drupal but no model data', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const mockDrupal = { ajax: jest.fn() };
      const onSave = jest.fn().mockReturnValue(null);

      render(<Toolbar {...defaultProps} drupal={mockDrupal} onSave={onSave} hasUnsavedChanges={true} />);

      fireEvent.click(screen.getByTitle('Save Model'));

      expect(consoleSpy).toHaveBeenCalledWith('No model data available to save');
      consoleSpy.mockRestore();
    });

    it('should handle save with model data but missing modeler_api settings', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const mockDrupal = { ajax: jest.fn() };
      const onSave = jest.fn().mockReturnValue({ nodes: [], edges: [] });

      render(
        <Toolbar
          {...defaultProps}
          drupal={mockDrupal}
          onSave={onSave}
          settings={{}}
          hasUnsavedChanges={true}
        />
      );

      fireEvent.click(screen.getByTitle('Save Model'));

      expect(consoleSpy).toHaveBeenCalledWith('modeler_api settings not found');
      consoleSpy.mockRestore();
    });

    it('should handle save with missing save_url', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const mockDrupal = { ajax: jest.fn() };
      const onSave = jest.fn().mockReturnValue({ nodes: [], edges: [] });

      render(
        <Toolbar
          {...defaultProps}
          drupal={mockDrupal}
          onSave={onSave}
          settings={{ modeler_api: { token_url: '/token' } }}
          hasUnsavedChanges={true}
        />
      );

      fireEvent.click(screen.getByTitle('Save Model'));

      expect(consoleSpy).toHaveBeenCalledWith('Missing modeler API URLs');
      consoleSpy.mockRestore();
    });

    it('should handle save with missing token_url', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const mockDrupal = { ajax: jest.fn() };
      const onSave = jest.fn().mockReturnValue({ nodes: [], edges: [] });

      render(
        <Toolbar
          {...defaultProps}
          drupal={mockDrupal}
          onSave={onSave}
          settings={{ modeler_api: { save_url: '/save' } }}
          hasUnsavedChanges={true}
        />
      );

      fireEvent.click(screen.getByTitle('Save Model'));

      expect(consoleSpy).toHaveBeenCalledWith('Missing modeler API URLs');
      consoleSpy.mockRestore();
    });

    it('should handle save when jQuery is not available', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const mockDrupal = { ajax: jest.fn() };
      const onSave = jest.fn().mockReturnValue({ nodes: [], edges: [] });
      (window as any).jQuery = undefined;

      render(
        <Toolbar
          {...defaultProps}
          drupal={mockDrupal}
          onSave={onSave}
          settings={{ modeler_api: { token_url: '/token', save_url: '/save' } }}
          hasUnsavedChanges={true}
        />
      );

      fireEvent.click(screen.getByTitle('Save Model'));

      expect(consoleSpy).toHaveBeenCalledWith('jQuery not available');
      consoleSpy.mockRestore();
    });

    it('should use workflowModelerData when onSave returns null', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const mockDrupal = { ajax: jest.fn() };
      const onSave = jest.fn().mockReturnValue(null);
      (window as any).workflowModelerData = { test: 'data' };
      (window as any).jQuery = undefined;

      render(
        <Toolbar
          {...defaultProps}
          drupal={mockDrupal}
          onSave={onSave}
          settings={{ modeler_api: { token_url: '/token', save_url: '/save' } }}
          hasUnsavedChanges={true}
        />
      );

      fireEvent.click(screen.getByTitle('Save Model'));

      // Should proceed past the model data check and fail on jQuery
      expect(consoleSpy).toHaveBeenCalledWith('jQuery not available');
      consoleSpy.mockRestore();
      delete (window as any).workflowModelerData;
    });
  });



  describe('read-only mode', () => {
    it('should hide save button when isReadOnly is true', () => {
      render(<Toolbar {...defaultProps} isReadOnly={true} />);
      expect(screen.queryByTitle('Save Model')).not.toBeInTheDocument();
    });

    it('should still show toolbar menu (settings) when isReadOnly is true', () => {
      render(<Toolbar {...defaultProps} isReadOnly={true} />);
      expect(screen.getByTestId('toolbar-menu')).toBeInTheDocument();
    });

    it('should still show close button when isReadOnly is true', () => {
      render(<Toolbar {...defaultProps} isReadOnly={true} />);
      expect(screen.getByTitle('Close Modeler')).toBeInTheDocument();
    });

    it('should hide quick-add event button when isReadOnly is true', () => {
      const onAddEvent = jest.fn();
      render(<Toolbar {...defaultProps} isReadOnly={true} onAddEvent={onAddEvent} />);
      expect(screen.queryByText('New event')).not.toBeInTheDocument();
    });

    it('should show save button when isReadOnly is false', () => {
      render(<Toolbar {...defaultProps} isReadOnly={false} />);
      expect(screen.getByTitle('Save Model')).toBeInTheDocument();
    });
  });
});
