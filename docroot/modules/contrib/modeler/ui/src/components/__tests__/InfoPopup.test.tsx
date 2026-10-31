import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import InfoPopup, { InfoItem } from '../InfoPopup';

describe('InfoPopup', () => {
  const defaultItems: InfoItem[] = [
    { label: 'ID', value: 'node_1' },
    { label: 'Type', value: 'action' },
    { label: 'Plugin ID', value: 'eca_trigger' },
  ];

  const defaultProps = {
    items: defaultItems,
    onClose: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render the popup with all visible items', () => {
      render(<InfoPopup {...defaultProps} />);

      expect(screen.getByText('ID')).toBeInTheDocument();
      expect(screen.getByText('node_1')).toBeInTheDocument();
      expect(screen.getByText('Type')).toBeInTheDocument();
      expect(screen.getByText('action')).toBeInTheDocument();
      expect(screen.getByText('Plugin ID')).toBeInTheDocument();
      expect(screen.getByText('eca_trigger')).toBeInTheDocument();
    });

    it('should return null when all items are hidden', () => {
      const items: InfoItem[] = [
        { label: 'ID', value: 'node_1', show: false },
        { label: 'Type', value: 'action', show: false },
      ];

      const { container } = render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      expect(container.firstChild).toBeNull();
    });

    it('should return null when items array is empty', () => {
      const { container } = render(<InfoPopup items={[]} onClose={defaultProps.onClose} />);

      expect(container.firstChild).toBeNull();
    });

    it('should filter out items with show: false', () => {
      const items: InfoItem[] = [
        { label: 'ID', value: 'node_1' },
        { label: 'Hidden', value: 'secret', show: false },
        { label: 'Type', value: 'action', show: true },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      expect(screen.getByText('ID')).toBeInTheDocument();
      expect(screen.getByText('Type')).toBeInTheDocument();
      expect(screen.queryByText('Hidden')).not.toBeInTheDocument();
      expect(screen.queryByText('secret')).not.toBeInTheDocument();
    });

    it('should show items without explicit show property (defaults to visible)', () => {
      const items: InfoItem[] = [
        { label: 'Visible', value: 'yes' },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      expect(screen.getByText('Visible')).toBeInTheDocument();
      expect(screen.getByText('yes')).toBeInTheDocument();
    });

    it('should show items with show: true', () => {
      const items: InfoItem[] = [
        { label: 'Explicit', value: 'shown', show: true },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      expect(screen.getByText('Explicit')).toBeInTheDocument();
    });

    it('should render React node values', () => {
      const items: InfoItem[] = [
        { label: 'Link', value: <a href="#test">Click me</a> },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      expect(screen.getByText('Click me')).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Click me' })).toBeInTheDocument();
    });
  });

  describe('error styling', () => {
    it('should apply error class when isError is true', () => {
      const items: InfoItem[] = [
        { label: 'Error', value: 'Something went wrong', isError: true },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      const valueElement = screen.getByText('Something went wrong');
      expect(valueElement).toHaveClass('info-popup-value', 'error');
    });

    it('should not apply error class when isError is false', () => {
      const items: InfoItem[] = [
        { label: 'Normal', value: 'All good', isError: false },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      const valueElement = screen.getByText('All good');
      expect(valueElement).toHaveClass('info-popup-value');
      expect(valueElement).not.toHaveClass('error');
    });

    it('should not apply error class when isError is undefined', () => {
      const items: InfoItem[] = [
        { label: 'Default', value: 'No error prop' },
      ];

      render(<InfoPopup items={items} onClose={defaultProps.onClose} />);

      const valueElement = screen.getByText('No error prop');
      expect(valueElement).toHaveClass('info-popup-value');
      expect(valueElement).not.toHaveClass('error');
    });
  });

  describe('click outside to close', () => {
    it('should call onClose when clicking outside the popup', () => {
      const onClose = jest.fn();
      render(<InfoPopup items={defaultItems} onClose={onClose} />);

      fireEvent.mouseDown(document.body);

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should not call onClose when clicking inside the popup', () => {
      const onClose = jest.fn();
      render(<InfoPopup items={defaultItems} onClose={onClose} />);

      const popup = document.querySelector('.info-popup')!;
      fireEvent.mouseDown(popup);

      expect(onClose).not.toHaveBeenCalled();
    });

    it('should not call onClose when clicking on a child element inside the popup', () => {
      const onClose = jest.fn();
      render(<InfoPopup items={defaultItems} onClose={onClose} />);

      const label = screen.getByText('ID');
      fireEvent.mouseDown(label);

      expect(onClose).not.toHaveBeenCalled();
    });
  });

  describe('keyboard interactions', () => {
    it('should call onClose when Escape key is pressed', () => {
      const onClose = jest.fn();
      render(<InfoPopup items={defaultItems} onClose={onClose} />);

      fireEvent.keyDown(document, { key: 'Escape' });

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should not call onClose for other key presses', () => {
      const onClose = jest.fn();
      render(<InfoPopup items={defaultItems} onClose={onClose} />);

      fireEvent.keyDown(document, { key: 'Enter' });
      fireEvent.keyDown(document, { key: 'Tab' });
      fireEvent.keyDown(document, { key: 'a' });

      expect(onClose).not.toHaveBeenCalled();
    });
  });

  describe('event listener cleanup', () => {
    it('should remove event listeners on unmount', () => {
      const addSpy = jest.spyOn(document, 'addEventListener');
      const removeSpy = jest.spyOn(document, 'removeEventListener');

      const { unmount } = render(<InfoPopup {...defaultProps} />);

      expect(addSpy).toHaveBeenCalledWith('mousedown', expect.any(Function));
      expect(addSpy).toHaveBeenCalledWith('keydown', expect.any(Function));

      unmount();

      expect(removeSpy).toHaveBeenCalledWith('mousedown', expect.any(Function));
      expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function));

      addSpy.mockRestore();
      removeSpy.mockRestore();
    });

    it('should not respond to events after unmount', () => {
      const onClose = jest.fn();
      const { unmount } = render(<InfoPopup items={defaultItems} onClose={onClose} />);

      unmount();

      fireEvent.mouseDown(document.body);
      fireEvent.keyDown(document, { key: 'Escape' });

      expect(onClose).not.toHaveBeenCalled();
    });
  });

  describe('accessibility', () => {
    it('should have role="dialog"', () => {
      render(<InfoPopup {...defaultProps} />);

      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('should have aria-modal="true"', () => {
      render(<InfoPopup {...defaultProps} />);

      expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
    });

    it('should have aria-label for the dialog', () => {
      render(<InfoPopup {...defaultProps} />);

      expect(screen.getByRole('dialog')).toHaveAttribute('aria-label', 'Metadata');
    });
  });

  describe('CSS classes', () => {
    it('should have correct root CSS class', () => {
      render(<InfoPopup {...defaultProps} />);

      expect(document.querySelector('.info-popup')).toBeInTheDocument();
    });

    it('should have correct content CSS class', () => {
      render(<InfoPopup {...defaultProps} />);

      expect(document.querySelector('.info-popup-content')).toBeInTheDocument();
    });

    it('should have correct item CSS classes', () => {
      render(<InfoPopup {...defaultProps} />);

      const items = document.querySelectorAll('.info-popup-item');
      expect(items).toHaveLength(3);
    });

    it('should have correct label and value CSS classes', () => {
      render(<InfoPopup {...defaultProps} />);

      const labels = document.querySelectorAll('.info-popup-label');
      const values = document.querySelectorAll('.info-popup-value');
      expect(labels).toHaveLength(3);
      expect(values).toHaveLength(3);
    });
  });
});
