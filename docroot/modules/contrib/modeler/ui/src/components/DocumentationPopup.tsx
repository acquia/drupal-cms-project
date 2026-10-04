import React, { useState, useEffect, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';
import { FiX, FiExternalLink, FiLoader } from 'react-icons/fi';
import { sanitizeHtml } from '../utils/sanitize';
import { t } from '../utils/translation';
import { validateDocumentationResponse } from '../utils/validation';
import { useFocusTrap } from '../hooks/useFocusTrap';

interface DocumentationPopupProps {
  url: string;
  title: string;
  isOpen: boolean;
  onClose: () => void;
}

/**
 * Process links in the HTML content:
 * - Remove mailto: links
 * - Convert relative URLs to absolute URLs based on the documentation URL
 * - Add target="_blank" and rel="noopener noreferrer" to all links
 */
export function processLinks(html: string, baseUrl: string): string {
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');
  
  // Get the base URL for resolving relative links
  let base: URL;
  try {
    base = new URL(baseUrl);
  } catch {
    // If baseUrl is invalid, return the original HTML
    return html;
  }
  
  // Find all anchor elements
  const links = doc.querySelectorAll('a');
  
  links.forEach(link => {
    const href = link.getAttribute('href');
    
    if (!href) {
      // No href, leave as is
      return;
    }
    
    // Remove pilcrow (¶) links entirely - these are typically paragraph anchors
    if (link.textContent?.trim() === '¶') {
      link.parentNode?.removeChild(link);
      return;
    }
    
    // Remove mailto: and javascript: links
    if (href.toLowerCase().startsWith('mailto:') || href.toLowerCase().startsWith('javascript:')) {
      // Replace the link with its text content
      const textNode = doc.createTextNode(link.textContent || '');
      link.parentNode?.replaceChild(textNode, link);
      return;
    }
    
    // For anchor-only links (just #something), remove the href to make them non-functional
    // but keep them as styled elements
    if (href.startsWith('#')) {
      link.removeAttribute('href');
      link.removeAttribute('target');
      link.style.cursor = 'default';
      return;
    }
    
    // Convert relative URLs to absolute
    if (!href.startsWith('http://') && !href.startsWith('https://') && !href.startsWith('//')) {
      try {
        const absoluteUrl = new URL(href, base).href;
        link.setAttribute('href', absoluteUrl);
      } catch {
        // If URL resolution fails, leave as is
      }
    }
    
    // Add target="_blank" and rel="noopener noreferrer" for external links
    link.setAttribute('target', '_blank');
    link.setAttribute('rel', 'noopener noreferrer');
  });
  
  return doc.body.innerHTML;
}

const DocumentationPopup: React.FC<DocumentationPopupProps> = ({
  url,
  title,
  isOpen,
  onClose,
}) => {
  const [content, setContent] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const popupRef = useRef<HTMLDivElement>(null);

  // Focus trap: keeps Tab inside the popup, Escape closes, restores focus on close
  useFocusTrap({
    isActive: isOpen,
    onClose,
    containerRef: popupRef,
    autoFocus: false, // We handle initial focus ourselves below
  });

  // Fetch documentation content
  const fetchDocumentation = useCallback(async () => {
    if (!url) return;

    setLoading(true);
    setError(null);
    setContent('');

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'text/html',
          'X-Requested-With': 'Workflow-Modeler-Documentation',
        },
      });

      // Validate HTTP status and Content-Type header
      validateDocumentationResponse(response);

      const html = await response.text();

      // Parse the HTML and extract the content from the specified selector
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');

      // Try to find the content using the data-md-component="content" selector
      let contentElement = doc.querySelector('[data-md-component="content"]');

      // Fallback selectors if the primary one is not found
      if (!contentElement) {
        contentElement = doc.querySelector('.md-content') ||
                        doc.querySelector('article') ||
                        doc.querySelector('main') ||
                        doc.querySelector('.content');
      }

      if (contentElement) {
        // Sanitize the HTML content, then process links
        const sanitizedContent = sanitizeHtml(contentElement.innerHTML);
        const processedContent = processLinks(sanitizedContent, url);
        setContent(processedContent);
      } else {
        // If no content element found, try to use the body content
        const bodyContent = doc.body?.innerHTML;
        if (bodyContent) {
          const sanitizedContent = sanitizeHtml(bodyContent);
          const processedContent = processLinks(sanitizedContent, url);
          setContent(processedContent);
        } else {
          setError(t('Could not find documentation content on the page.'));
        }
      }
    } catch (err) {
      console.error('Error fetching documentation:', err);
      setError(err instanceof Error ? err.message : t('Failed to load documentation'));
    } finally {
      setLoading(false);
    }
  }, [url]);

  // Fetch content when popup opens and focus the popup for keyboard events
  useEffect(() => {
    if (isOpen && url) {
      fetchDocumentation();
      // Focus the popup so it can receive keyboard events
      setTimeout(() => {
        popupRef.current?.focus();
      }, 0);
    }
  }, [isOpen, url, fetchDocumentation]);

  // Handle click outside to close
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (popupRef.current && !popupRef.current.contains(event.target as Node)) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen, onClose]);

  // Note: Escape key handling is provided by useFocusTrap above

  if (!isOpen) return null;

  // Use portal to render inside the .modeler container so it inherits
  // our scoped CSS custom properties and is shielded by all: revert.
  const portalTarget = document.querySelector('.modeler') || document.body;

  return createPortal(
    <div 
      className="documentation-popup-overlay"
      onPointerDown={(e) => e.stopPropagation()}
      onMouseDown={(e) => e.stopPropagation()}
      onClick={(e) => e.stopPropagation()}
    >
      <div className="documentation-popup" ref={popupRef} tabIndex={-1} role="dialog" aria-modal="true" aria-labelledby="documentation-popup-title">
        <div className="documentation-popup-header">
          <h3 id="documentation-popup-title">{title}</h3>
          <div className="documentation-popup-actions">
            <a
              href={url}
              target="_blank"
              rel="noopener noreferrer"
              className="documentation-external-link"
              title={t('Open in new tab')}
              aria-label={t('Open in new tab')}
            >
              <FiExternalLink />
            </a>
            <button
              type="button"
              className="documentation-close-btn"
              onClick={(e) => {
                e.stopPropagation();
                onClose();
              }}
              title={t('Close')}
              aria-label={t('Close')}
            >
              <FiX />
            </button>
          </div>
        </div>
        <div className="documentation-popup-content">
          {loading && (
            <div className="documentation-loading">
              <FiLoader className="spinning" />
              <span>{t('Loading documentation...')}</span>
            </div>
          )}
          {error && (
            <div className="documentation-error">
              <p>{error}</p>
              <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="documentation-fallback-link"
              >
                {t('Open documentation in new tab')}
              </a>
            </div>
          )}
          {!loading && !error && content && (
            <div
              className="documentation-body"
              dangerouslySetInnerHTML={{ __html: content }}
            />
          )}
        </div>
      </div>
    </div>,
    portalTarget
  );
};

export default DocumentationPopup;
