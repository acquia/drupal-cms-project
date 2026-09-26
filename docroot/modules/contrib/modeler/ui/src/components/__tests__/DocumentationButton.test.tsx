import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import DocumentationButton from '../DocumentationButton';

// Mock the DocumentationPopup component
jest.mock('../DocumentationPopup', () => {
  return function MockDocumentationPopup({ isOpen, onClose, title, url }: any) {
    if (!isOpen) return null;
    return (
      <div data-testid="documentation-popup">
        <span data-testid="popup-title">{title}</span>
        <span data-testid="popup-url">{url}</span>
        <button onClick={onClose} data-testid="close-popup">Close</button>
      </div>
    );
  };
});

describe('DocumentationButton', () => {
  const defaultProps = {
    url: 'https://example.com/docs',
    title: 'Test Component',
  };

  describe('rendering', () => {
    it('should not render when url is null', () => {
      const { container } = render(<DocumentationButton url={null} title="Test" />);
      expect(container.firstChild).toBeNull();
    });

    it('should not render when url is undefined', () => {
      const { container } = render(<DocumentationButton url={undefined} title="Test" />);
      expect(container.firstChild).toBeNull();
    });

    it('should not render when url is empty string', () => {
      const { container } = render(<DocumentationButton url="" title="Test" />);
      expect(container.firstChild).toBeNull();
    });

    it('should render button when url is provided', () => {
      render(<DocumentationButton {...defaultProps} />);
      expect(screen.getByRole('button')).toBeInTheDocument();
    });

    it('should have correct title attribute', () => {
      render(<DocumentationButton {...defaultProps} />);
      expect(screen.getByRole('button')).toHaveAttribute(
        'title',
        'View documentation for Test Component'
      );
    });

    it('should apply custom className', () => {
      render(<DocumentationButton {...defaultProps} className="custom-class" />);
      expect(screen.getByRole('button')).toHaveClass('documentation-btn', 'custom-class');
    });
  });

  describe('popup interaction', () => {
    it('should not show popup initially', () => {
      render(<DocumentationButton {...defaultProps} />);
      expect(screen.queryByTestId('documentation-popup')).not.toBeInTheDocument();
    });

    it('should show popup when button is clicked', () => {
      render(<DocumentationButton {...defaultProps} />);
      
      fireEvent.click(screen.getByRole('button'));
      
      expect(screen.getByTestId('documentation-popup')).toBeInTheDocument();
    });

    it('should pass correct props to popup', () => {
      render(<DocumentationButton {...defaultProps} />);
      
      fireEvent.click(screen.getByRole('button'));
      
      expect(screen.getByTestId('popup-title')).toHaveTextContent('Test Component');
      expect(screen.getByTestId('popup-url')).toHaveTextContent('https://example.com/docs');
    });

    it('should close popup when onClose is called', () => {
      render(<DocumentationButton {...defaultProps} />);
      
      // Open popup
      fireEvent.click(screen.getByRole('button'));
      expect(screen.getByTestId('documentation-popup')).toBeInTheDocument();
      
      // Close popup
      fireEvent.click(screen.getByTestId('close-popup'));
      expect(screen.queryByTestId('documentation-popup')).not.toBeInTheDocument();
    });

    it('should prevent event propagation when clicked', () => {
      const parentClickHandler = jest.fn();
      
      render(
        <div onClick={parentClickHandler}>
          <DocumentationButton {...defaultProps} />
        </div>
      );
      
      fireEvent.click(screen.getByRole('button'));
      
      expect(parentClickHandler).not.toHaveBeenCalled();
    });
  });

  describe('keyboard interaction', () => {
    it('should open popup on Enter key', () => {
      render(<DocumentationButton {...defaultProps} />);
      
      fireEvent.keyDown(screen.getByRole('button'), { key: 'Enter' });
      
      expect(screen.getByTestId('documentation-popup')).toBeInTheDocument();
    });

    it('should open popup on Space key', () => {
      render(<DocumentationButton {...defaultProps} />);
      
      fireEvent.keyDown(screen.getByRole('button'), { key: ' ' });
      
      expect(screen.getByTestId('documentation-popup')).toBeInTheDocument();
    });

    it('should not open popup on other keys', () => {
      render(<DocumentationButton {...defaultProps} />);
      
      fireEvent.keyDown(screen.getByRole('button'), { key: 'Tab' });
      
      expect(screen.queryByTestId('documentation-popup')).not.toBeInTheDocument();
    });

    it('should prevent propagation on keyboard activation', () => {
      const parentKeyHandler = jest.fn();
      
      render(
        <div onKeyDown={parentKeyHandler}>
          <DocumentationButton {...defaultProps} />
        </div>
      );
      
      fireEvent.keyDown(screen.getByRole('button'), { key: 'Enter' });
      
      expect(parentKeyHandler).not.toHaveBeenCalled();
    });
  });
});
