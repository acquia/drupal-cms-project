import DOMPurify from 'dompurify';

/**
 * Sanitize HTML content to prevent XSS attacks.
 * Uses DOMPurify with a restrictive configuration.
 */
export const sanitizeHtml = (dirty: string | null | undefined): string => {
  if (!dirty || typeof dirty !== 'string') return '';

  return DOMPurify.sanitize(dirty, {
    // Allow common formatting tags
    ALLOWED_TAGS: [
      'a', 'b', 'i', 'em', 'strong', 'u', 's', 'strike',
      'p', 'br', 'hr',
      'ul', 'ol', 'li',
      'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
      'blockquote', 'pre', 'code',
      'span', 'div',
      'table', 'thead', 'tbody', 'tr', 'th', 'td',
      'img', 'figure', 'figcaption',
      'dl', 'dt', 'dd',
      'abbr', 'cite', 'q', 'sub', 'sup', 'small', 'mark'
    ],
    // Allow safe attributes
    ALLOWED_ATTR: [
      'href', 'src', 'alt', 'title', 'class', 'id',
      'target', 'rel',
      'colspan', 'rowspan',
      'width', 'height',
      'data-token', 'contenteditable', 'draggable'
    ],
    // Force all links to open in new tab and add noopener
    ADD_ATTR: ['target', 'rel'],
    // Additional security
    ALLOW_DATA_ATTR: false,
    USE_PROFILES: { html: true },
    // Prevent DOM clobbering
    SANITIZE_DOM: true,
    // Prevent prototype pollution
    SANITIZE_NAMED_PROPS: true
  });
};

/**
 * Allows config-token spans and native contenteditable line containers.
 */
export const sanitizeTokenHtml = (dirty: string | null | undefined): string => {
  if (!dirty || typeof dirty !== 'string') return '';

  return DOMPurify.sanitize(dirty, {
    ALLOWED_TAGS: ['span', 'br', 'div', 'p'],
    ALLOWED_ATTR: ['class', 'data-token', 'contenteditable', 'title', 'draggable'],
    ALLOW_DATA_ATTR: true, // Allow data-token attribute
    SANITIZE_DOM: true,
    SANITIZE_NAMED_PROPS: true
  });
};

/**
 * Sanitize plain text by escaping HTML entities.
 * Use this for user input that should never contain HTML.
 */
export const escapeHtml = (text: string | null | undefined): string => {
  if (!text || typeof text !== 'string') return '';

  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
};



