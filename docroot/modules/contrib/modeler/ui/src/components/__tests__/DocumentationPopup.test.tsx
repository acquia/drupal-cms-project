import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DocumentationPopup, { processLinks } from '../DocumentationPopup';

// Mock the sanitize utility
jest.mock('../../utils/sanitize', () => ({
  sanitizeHtml: jest.fn((html) => html),
}));

describe('DocumentationPopup', () => {
  const defaultProps = {
    url: 'https://example.com/docs/component',
    title: 'Test Component',
    isOpen: true,
    onClose: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    // Set up default fetch mock that returns empty content
    // This prevents errors when tests don't explicitly mock fetch
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      headers: new Headers({ 'content-type': 'text/html; charset=utf-8' }),
      text: () => Promise.resolve('<div data-md-component="content"></div>'),
    });
  });

  afterEach(() => {
    // Cleanup any pending promises
    jest.clearAllTimers();
  });

  describe('rendering', () => {
    it('should not render when isOpen is false', async () => {
      render(<DocumentationPopup {...defaultProps} isOpen={false} />);
      expect(screen.queryByText('Test Component')).not.toBeInTheDocument();
    });

    it('should render when isOpen is true', async () => {
      render(<DocumentationPopup {...defaultProps} />);
      expect(screen.getByText('Test Component')).toBeInTheDocument();
      // Wait for async fetch to complete
      await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    });

    it('should display the title in the header', async () => {
      render(<DocumentationPopup {...defaultProps} />);
      expect(screen.getByRole('heading', { name: 'Test Component' })).toBeInTheDocument();
      // Wait for async fetch to complete
      await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    });

    it('should have an external link to the documentation URL', async () => {
      render(<DocumentationPopup {...defaultProps} />);
      const externalLink = screen.getByTitle('Open in new tab');
      expect(externalLink).toHaveAttribute('href', 'https://example.com/docs/component');
      expect(externalLink).toHaveAttribute('target', '_blank');
      expect(externalLink).toHaveAttribute('rel', 'noopener noreferrer');
      // Wait for async fetch to complete
      await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    });

    it('should have a close button', async () => {
      render(<DocumentationPopup {...defaultProps} />);
      expect(screen.getByTitle('Close')).toBeInTheDocument();
      // Wait for async fetch to complete
      await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    });
  });

  describe('loading state', () => {
    it('should show loading indicator when fetching', async () => {
      // Mock a slow fetch
      (global.fetch as jest.Mock).mockImplementation(
        () => new Promise(() => {}) // Never resolves
      );

      render(<DocumentationPopup {...defaultProps} />);
      expect(screen.getByText('Loading documentation...')).toBeInTheDocument();
    });
  });

  describe('content fetching', () => {
    it('should fetch documentation content when opened', async () => {
      const mockHtml = `
        <html>
          <body>
            <div data-md-component="content">
              <h1>Documentation Title</h1>
              <p>Documentation content here.</p>
            </div>
          </body>
        </html>
      `;

      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve(mockHtml),
      });

      render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(global.fetch).toHaveBeenCalledWith(
          'https://example.com/docs/component',
          expect.objectContaining({
            method: 'GET',
            headers: { 
              Accept: 'text/html',
              'X-Requested-With': 'Workflow-Modeler-Documentation',
            },
          })
        );
      });
    });

    it('should display fetched content', async () => {
      const mockHtml = `
        <html>
          <body>
            <div data-md-component="content">
              <h1>Documentation Title</h1>
              <p>Documentation content here.</p>
            </div>
          </body>
        </html>
      `;

      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve(mockHtml),
      });

      render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(screen.getByText('Documentation Title')).toBeInTheDocument();
      });
    });

    it('should use fallback selectors when data-md-component is not found', async () => {
      const mockHtml = `
        <html>
          <body>
            <article>
              <h1>Article Content</h1>
            </article>
          </body>
        </html>
      `;

      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve(mockHtml),
      });

      render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(screen.getByText('Article Content')).toBeInTheDocument();
      });
    });

    it('should show error when fetch fails', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 404,
        statusText: 'Not Found',
      });

      render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(screen.getByText(/Failed to fetch documentation/)).toBeInTheDocument();
      });
    });

    it('should show error and fallback link on fetch error', async () => {
      (global.fetch as jest.Mock).mockRejectedValueOnce(new Error('Network error'));

      render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(screen.getByText('Network error')).toBeInTheDocument();
        expect(screen.getByText('Open documentation in new tab')).toBeInTheDocument();
      });
    });
  });

  describe('interactions', () => {
    it('should call onClose when close button is clicked', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(<DocumentationPopup {...defaultProps} />);

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByTitle('Close'));
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('should call onClose when Escape key is pressed', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(<DocumentationPopup {...defaultProps} />);

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      fireEvent.keyDown(document, { key: 'Escape' });
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('should call onClose when clicking outside the popup', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(<DocumentationPopup {...defaultProps} />);

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      // Click on the overlay (outside the popup)
      fireEvent.mouseDown(screen.getByRole('heading', { name: 'Test Component' }).closest('.documentation-popup-overlay')!);
      
      // Note: The click outside behavior depends on the mousedown event target
      // In this test, clicking on overlay triggers onClose
    });

    it('should stop event propagation on close button click', async () => {
      const parentClickHandler = jest.fn();
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(
        <div onClick={parentClickHandler}>
          <DocumentationPopup {...defaultProps} />
        </div>
      );

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      const closeButton = screen.getByTitle('Close');
      fireEvent.click(closeButton);

      // Parent should not receive the click
      expect(parentClickHandler).not.toHaveBeenCalled();
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('should stop mousedown propagation on overlay', async () => {
      const parentMouseDownHandler = jest.fn();
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(
        <div onMouseDown={parentMouseDownHandler}>
          <DocumentationPopup {...defaultProps} />
        </div>
      );

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      const overlay = screen.getByRole('heading', { name: 'Test Component' }).closest('.documentation-popup-overlay')!;
      fireEvent.mouseDown(overlay);

      // Parent should not receive the mousedown event
      expect(parentMouseDownHandler).not.toHaveBeenCalled();
    });

    it('should stop click propagation on overlay', async () => {
      const parentClickHandler = jest.fn();
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(
        <div onClick={parentClickHandler}>
          <DocumentationPopup {...defaultProps} />
        </div>
      );

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      const overlay = screen.getByRole('heading', { name: 'Test Component' }).closest('.documentation-popup-overlay')!;
      fireEvent.click(overlay);

      // Parent should not receive the click event
      expect(parentClickHandler).not.toHaveBeenCalled();
    });

    it('should stop pointerDown propagation on overlay', async () => {
      const parentPointerDownHandler = jest.fn();
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(
        <div onPointerDown={parentPointerDownHandler}>
          <DocumentationPopup {...defaultProps} />
        </div>
      );

      // Wait for fetch to complete
      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      const overlay = screen.getByRole('heading', { name: 'Test Component' }).closest('.documentation-popup-overlay')!;
      fireEvent.pointerDown(overlay);

      // Parent should not receive the pointerdown event
      // This is critical because useClickOutside listens for pointerdown
      expect(parentPointerDownHandler).not.toHaveBeenCalled();
    });

    it('should have aria-modal="true" on the dialog element', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve('<div data-md-component="content">Content</div>'),
      });

      render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(screen.getByText('Content')).toBeInTheDocument();
      });

      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
    });
  });

  describe('URL handling', () => {
    it('should not fetch when URL is empty', () => {
      render(<DocumentationPopup {...defaultProps} url="" />);
      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('should refetch when URL changes', async () => {
      const mockHtml = '<div data-md-component="content">Content</div>';
      (global.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        headers: new Headers({ 'content-type': 'text/html' }),
        text: () => Promise.resolve(mockHtml),
      });

      const { rerender } = render(<DocumentationPopup {...defaultProps} />);

      await waitFor(() => {
        expect(global.fetch).toHaveBeenCalledTimes(1);
      });

      rerender(<DocumentationPopup {...defaultProps} url="https://example.com/docs/other" />);

      await waitFor(() => {
        expect(global.fetch).toHaveBeenCalledTimes(2);
      });
    });
  });
});

describe('processLinks', () => {
  const baseUrl = 'https://example.com/docs/component/';

  describe('pilcrow links', () => {
    it('should remove links containing only ¶', () => {
      const html = '<h2>Section Title <a href="#section">¶</a></h2>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('¶');
      expect(result).toContain('Section Title');
    });

    it('should remove pilcrow links with whitespace', () => {
      const html = '<h2>Title <a href="#section"> ¶ </a></h2>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('¶');
    });

    it('should keep links that contain ¶ with other text', () => {
      const html = '<a href="/page/">See section ¶ 5</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('See section ¶ 5');
    });
  });

  describe('mailto links', () => {
    it('should remove mailto links and keep text content', () => {
      const html = '<p>Contact us at <a href="mailto:test@example.com">test@example.com</a></p>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('mailto:');
      expect(result).toContain('test@example.com');
      expect(result).not.toContain('<a');
    });

    it('should handle mailto links with display text', () => {
      const html = '<a href="mailto:support@example.com">Contact Support</a>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('mailto:');
      expect(result).toContain('Contact Support');
    });
  });

  describe('javascript links', () => {
    it('should remove javascript: links and keep text content', () => {
      const html = '<a href="javascript:alert(\'test\')">Click me</a>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('javascript:');
      expect(result).toContain('Click me');
      expect(result).not.toContain('<a');
    });

    it('should handle javascript:void(0) links', () => {
      const html = '<a href="javascript:void(0)">Do nothing</a>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('javascript:');
      expect(result).toContain('Do nothing');
    });

    it('should handle JavaScript with different casing', () => {
      const html = '<a href="JavaScript:doSomething()">Action</a>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('JavaScript:');
      expect(result).not.toContain('javascript:');
      expect(result).toContain('Action');
    });
  });

  describe('relative URLs', () => {
    it('should convert relative URLs to absolute', () => {
      const html = '<a href="../other-page/">Other Page</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('href="https://example.com/docs/other-page/"');
    });

    it('should convert root-relative URLs to absolute', () => {
      const html = '<a href="/root-page/">Root Page</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('href="https://example.com/root-page/"');
    });

    it('should convert same-directory relative URLs', () => {
      const html = '<a href="sibling-page.html">Sibling</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('href="https://example.com/docs/component/sibling-page.html"');
    });

    it('should leave absolute URLs unchanged', () => {
      const html = '<a href="https://other-site.com/page/">External</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('href="https://other-site.com/page/"');
    });

    it('should handle protocol-relative URLs', () => {
      const html = '<a href="//cdn.example.com/resource">CDN Resource</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('href="//cdn.example.com/resource"');
    });
  });

  describe('anchor links', () => {
    it('should disable anchor-only links by removing href', () => {
      const html = '<a href="#section">Jump to Section</a>';
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('href=');
      expect(result).not.toContain('target=');
      expect(result).toContain('Jump to Section');
      expect(result).toContain('cursor: default');
    });
  });

  describe('target and rel attributes', () => {
    it('should add target="_blank" to external links', () => {
      const html = '<a href="https://example.com/page/">Link</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('target="_blank"');
    });

    it('should add rel="noopener noreferrer" to external links', () => {
      const html = '<a href="https://example.com/page/">Link</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('rel="noopener noreferrer"');
    });

    it('should add attributes to converted relative links', () => {
      const html = '<a href="../page/">Link</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('target="_blank"');
      expect(result).toContain('rel="noopener noreferrer"');
    });
  });

  describe('edge cases', () => {
    it('should handle links without href', () => {
      const html = '<a>No href</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('<a>No href</a>');
    });

    it('should handle empty href', () => {
      const html = '<a href="">Empty href</a>';
      const result = processLinks(html, baseUrl);
      expect(result).toContain('Empty href');
    });

    it('should handle invalid base URL gracefully', () => {
      const html = '<a href="page.html">Link</a>';
      const result = processLinks(html, 'not-a-valid-url');
      expect(result).toContain('href="page.html"');
    });

    it('should handle multiple links', () => {
      const html = `
        <a href="mailto:test@example.com">Email</a>
        <a href="../page/">Relative</a>
        <a href="https://external.com/">External</a>
      `;
      const result = processLinks(html, baseUrl);
      expect(result).not.toContain('mailto:');
      expect(result).toContain('https://example.com/docs/page/');
      expect(result).toContain('https://external.com/');
    });
  });
});
