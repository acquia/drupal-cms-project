import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import ToolbarMenu from '../ToolbarMenu';

// Capture the close callback passed to useClickOutside so tests can invoke it
let clickOutsideCallback: (() => void) | null = null;
jest.mock('../../hooks/useClickOutside', () => ({
  useClickOutside: jest.fn((_isOpen: boolean, _refs: unknown[], callback: () => void) => {
    clickOutsideCallback = callback;
  }),
}));

// Mock useUISettingsStore with controllable darkMode state
let mockDarkMode = false;
const mockToggleDarkMode = jest.fn();
jest.mock('../../store/useUISettingsStore', () => ({
  useUISettingsStore: jest.fn((selector: unknown) => {
    const state = {
      darkMode: mockDarkMode,
      toggleDarkMode: mockToggleDarkMode,
    };
    return typeof selector === 'function' ? (selector as (s: typeof state) => unknown)(state) : state;
  }),
}));

describe('ToolbarMenu', () => {
  const defaultProps = {
    onOpenMetadata: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    clickOutsideCallback = null;
    mockDarkMode = false;
  });

  function openMenu() {
    const trigger = screen.getByRole('button', { name: 'More options' });
    fireEvent.click(trigger);
    return trigger;
  }

  describe('trigger button', () => {
    it('renders with correct aria attributes when closed', () => {
      render(<ToolbarMenu {...defaultProps} />);
      const trigger = screen.getByRole('button', { name: 'More options' });
      expect(trigger).toBeInTheDocument();
      expect(trigger).toHaveAttribute('aria-haspopup', 'menu');
      expect(trigger).toHaveAttribute('aria-expanded', 'false');
    });

    it('sets aria-expanded to true when menu is open', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      const trigger = screen.getByRole('button', { name: 'More options' });
      expect(trigger).toHaveAttribute('aria-expanded', 'true');
    });

    it('calls preventDefault and stopPropagation on toggle', () => {
      render(<ToolbarMenu {...defaultProps} />);
      const trigger = screen.getByRole('button', { name: 'More options' });

      const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
      const preventDefaultSpy = jest.spyOn(clickEvent, 'preventDefault');
      const stopPropagationSpy = jest.spyOn(clickEvent, 'stopPropagation');

      fireEvent(trigger, clickEvent);

      expect(preventDefaultSpy).toHaveBeenCalled();
      expect(stopPropagationSpy).toHaveBeenCalled();
    });

    it('toggles menu closed on second click', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.getByRole('menu')).toBeInTheDocument();

      // Click again to close
      openMenu();
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  describe('dropdown menu', () => {
    it('does not render dropdown when closed', () => {
      render(<ToolbarMenu {...defaultProps} />);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('renders dropdown with menu role when open', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.getByRole('menu')).toBeInTheDocument();
    });
  });

  describe('Model Settings item', () => {
    it('renders Model Settings menu item', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.getByText('Model Settings')).toBeInTheDocument();
    });

    it('calls onOpenMetadata and closes menu on click', () => {
      const onOpenMetadata = jest.fn();
      render(<ToolbarMenu onOpenMetadata={onOpenMetadata} />);
      openMenu();

      fireEvent.click(screen.getByText('Model Settings').closest('[role="menuitem"]')!);

      expect(onOpenMetadata).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  describe('dark mode toggle', () => {
    it('shows "Switch to Dark Mode" when dark mode is off', () => {
      mockDarkMode = false;
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.getByText('Switch to Dark Mode')).toBeInTheDocument();
    });

    it('shows "Switch to Light Mode" when dark mode is on', () => {
      mockDarkMode = true;
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.getByText('Switch to Light Mode')).toBeInTheDocument();
    });

    it('calls toggleDarkMode and closes menu on click', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      fireEvent.click(screen.getByText('Switch to Dark Mode').closest('[role="menuitem"]')!);

      expect(mockToggleDarkMode).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('applies active class when dark mode is on', () => {
      mockDarkMode = true;
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const darkModeItem = screen.getByText('Switch to Light Mode').closest('[role="menuitem"]')!;
      expect(darkModeItem).toHaveClass('active');
    });

    it('does not apply active class when dark mode is off', () => {
      mockDarkMode = false;
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const darkModeItem = screen.getByText('Switch to Dark Mode').closest('[role="menuitem"]')!;
      expect(darkModeItem).not.toHaveClass('active');
    });
  });

  describe('Export Model item', () => {
    it('shows Export Model when canExport and onExport are provided', () => {
      render(
        <ToolbarMenu {...defaultProps} canExport={true} onExport={jest.fn()} />,
      );
      openMenu();
      expect(screen.getByText('Export Model')).toBeInTheDocument();
    });

    it('does not show Export Model when canExport is false', () => {
      render(
        <ToolbarMenu {...defaultProps} canExport={false} onExport={jest.fn()} />,
      );
      openMenu();
      expect(screen.queryByText('Export Model')).not.toBeInTheDocument();
    });

    it('does not show Export Model when onExport is not provided', () => {
      render(<ToolbarMenu {...defaultProps} canExport={true} />);
      openMenu();
      expect(screen.queryByText('Export Model')).not.toBeInTheDocument();
    });

    it('does not show Export Model by default', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.queryByText('Export Model')).not.toBeInTheDocument();
    });

    it('calls onExport and closes menu on click', () => {
      const onExport = jest.fn();
      render(
        <ToolbarMenu {...defaultProps} canExport={true} onExport={onExport} />,
      );
      openMenu();

      fireEvent.click(screen.getByText('Export Model').closest('[role="menuitem"]')!);

      expect(onExport).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  describe('keyboard navigation', () => {
    it('triggers action on Enter key', () => {
      const onOpenMetadata = jest.fn();
      render(<ToolbarMenu onOpenMetadata={onOpenMetadata} />);
      openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      fireEvent.keyDown(settingsItem, { key: 'Enter' });

      expect(onOpenMetadata).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('triggers action on Space key', () => {
      const onOpenMetadata = jest.fn();
      render(<ToolbarMenu onOpenMetadata={onOpenMetadata} />);
      openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      fireEvent.keyDown(settingsItem, { key: ' ' });

      expect(onOpenMetadata).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('calls preventDefault on Enter key', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      const keyEvent = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true,
      });
      const preventDefaultSpy = jest.spyOn(keyEvent, 'preventDefault');

      fireEvent(settingsItem, keyEvent);

      expect(preventDefaultSpy).toHaveBeenCalled();
    });

    it('calls preventDefault on Space key', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      const keyEvent = new KeyboardEvent('keydown', {
        key: ' ',
        bubbles: true,
        cancelable: true,
      });
      const preventDefaultSpy = jest.spyOn(keyEvent, 'preventDefault');

      fireEvent(settingsItem, keyEvent);

      expect(preventDefaultSpy).toHaveBeenCalled();
    });

    it('closes menu and focuses trigger on Escape key', () => {
      render(<ToolbarMenu {...defaultProps} />);
      const trigger = openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      fireEvent.keyDown(settingsItem, { key: 'Escape' });

      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
      expect(document.activeElement).toBe(trigger);
    });

    it('does not trigger action on other keys', () => {
      const onOpenMetadata = jest.fn();
      render(<ToolbarMenu onOpenMetadata={onOpenMetadata} />);
      openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      fireEvent.keyDown(settingsItem, { key: 'Tab' });

      expect(onOpenMetadata).not.toHaveBeenCalled();
      // Menu should still be open
      expect(screen.getByRole('menu')).toBeInTheDocument();
    });
  });

  describe('click outside', () => {
    it('closes menu when click outside is triggered', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();
      expect(screen.getByRole('menu')).toBeInTheDocument();

      // Simulate click outside by invoking the captured callback
      expect(clickOutsideCallback).not.toBeNull();
      act(() => {
        clickOutsideCallback!();
      });

      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  describe('menu item rendering', () => {
    it('renders menu items with correct structure', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const menuItems = screen.getAllByRole('menuitem');
      // Should have 3 items: Settings, Dark Mode, Documentation (no export by default)
      expect(menuItems).toHaveLength(3);
    });

    it('renders 4 menu items when export is available', () => {
      render(
        <ToolbarMenu {...defaultProps} canExport={true} onExport={jest.fn()} />,
      );
      openMenu();

      const menuItems = screen.getAllByRole('menuitem');
      expect(menuItems).toHaveLength(4);
    });

    it('menu items have icon and label spans', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const settingsItem = screen.getByText('Model Settings').closest('[role="menuitem"]')!;
      expect(settingsItem.querySelector('.toolbar-menu-item-icon')).toBeInTheDocument();
      expect(settingsItem.querySelector('.toolbar-menu-item-label')).toBeInTheDocument();
    });

    it('menu items have correct tabIndex', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const menuItems = screen.getAllByRole('menuitem');
      menuItems.forEach(item => {
        expect(item).toHaveAttribute('tabindex', '0');
      });
    });

    it('renders items with toolbar-menu-item class', () => {
      render(<ToolbarMenu {...defaultProps} />);
      openMenu();

      const menuItems = screen.getAllByRole('menuitem');
      menuItems.forEach(item => {
        expect(item).toHaveClass('toolbar-menu-item');
      });
    });
  });
});
