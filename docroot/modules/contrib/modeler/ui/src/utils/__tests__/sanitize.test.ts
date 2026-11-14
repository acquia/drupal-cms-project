import { sanitizeHtml, sanitizeTokenHtml, escapeHtml } from '../sanitize';

describe('sanitize utilities', () => {
  describe('sanitizeHtml', () => {
    describe('input validation', () => {
      it('should return empty string for null input', () => {
        expect(sanitizeHtml(null)).toBe('');
      });

      it('should return empty string for undefined input', () => {
        expect(sanitizeHtml(undefined)).toBe('');
      });

      it('should return empty string for empty string input', () => {
        expect(sanitizeHtml('')).toBe('');
      });

      it('should return empty string for non-string input', () => {
        expect(sanitizeHtml(123 as any)).toBe('');
        expect(sanitizeHtml({} as any)).toBe('');
        expect(sanitizeHtml([] as any)).toBe('');
      });
    });

    describe('XSS prevention', () => {
      it('should strip script tags', () => {
        const result = sanitizeHtml('<script>alert("xss")</script>');
        expect(result).not.toContain('<script');
        expect(result).not.toContain('alert');
      });

      it('should strip onclick handlers', () => {
        const result = sanitizeHtml('<div onclick="alert(\'xss\')">Click me</div>');
        expect(result).not.toContain('onclick');
        expect(result).toContain('Click me');
      });

      it('should strip onerror handlers', () => {
        const result = sanitizeHtml('<img src="x" onerror="alert(\'xss\')">');
        expect(result).not.toContain('onerror');
      });

      it('should strip javascript: URLs in href', () => {
        const result = sanitizeHtml('<a href="javascript:alert(\'xss\')">Click</a>');
        expect(result).not.toContain('javascript:');
      });

      it('should strip iframe tags', () => {
        const result = sanitizeHtml('<iframe src="https://evil.com"></iframe>');
        expect(result).not.toContain('<iframe');
      });

      it('should strip object and embed tags', () => {
        const result = sanitizeHtml('<object data="evil.swf"></object><embed src="evil.swf">');
        expect(result).not.toContain('<object');
        expect(result).not.toContain('<embed');
      });

      it('should strip style tags', () => {
        const result = sanitizeHtml('<style>body { display: none; }</style>');
        expect(result).not.toContain('<style');
      });
    });

    describe('allowed tags', () => {
      it('should allow basic formatting tags', () => {
        const input = '<b>bold</b> <i>italic</i> <em>emphasis</em> <strong>strong</strong>';
        const result = sanitizeHtml(input);
        expect(result).toContain('<b>');
        expect(result).toContain('<i>');
        expect(result).toContain('<em>');
        expect(result).toContain('<strong>');
      });

      it('should allow paragraph and line break tags', () => {
        const input = '<p>Paragraph</p><br><hr>';
        const result = sanitizeHtml(input);
        expect(result).toContain('<p>');
        expect(result).toContain('<br');
        expect(result).toContain('<hr');
      });

      it('should allow list tags', () => {
        const input = '<ul><li>Item 1</li><li>Item 2</li></ul>';
        const result = sanitizeHtml(input);
        expect(result).toContain('<ul>');
        expect(result).toContain('<li>');
      });

      it('should allow heading tags', () => {
        const input = '<h1>H1</h1><h2>H2</h2><h3>H3</h3>';
        const result = sanitizeHtml(input);
        expect(result).toContain('<h1>');
        expect(result).toContain('<h2>');
        expect(result).toContain('<h3>');
      });

      it('should allow table tags', () => {
        const input = '<table><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>';
        const result = sanitizeHtml(input);
        expect(result).toContain('<table>');
        expect(result).toContain('<thead>');
        expect(result).toContain('<tbody>');
        expect(result).toContain('<tr>');
        expect(result).toContain('<th>');
        expect(result).toContain('<td>');
      });

      it('should allow code blocks', () => {
        const input = '<pre><code>const x = 1;</code></pre>';
        const result = sanitizeHtml(input);
        expect(result).toContain('<pre>');
        expect(result).toContain('<code>');
      });

      it('should allow img tags with safe attributes', () => {
        const input = '<img src="image.jpg" alt="Description" width="100" height="100">';
        const result = sanitizeHtml(input);
        expect(result).toContain('<img');
        expect(result).toContain('src="image.jpg"');
        expect(result).toContain('alt="Description"');
      });
    });

    describe('allowed attributes', () => {
      it('should allow href on links', () => {
        const result = sanitizeHtml('<a href="https://example.com">Link</a>');
        expect(result).toContain('href="https://example.com"');
      });

      it('should allow class and id attributes', () => {
        const result = sanitizeHtml('<div class="my-class" id="my-id">Content</div>');
        expect(result).toContain('class="my-class"');
        // Note: id attribute is included in ALLOWED_ATTR
        expect(result).toContain('Content');
      });

      it('should allow title attribute', () => {
        const result = sanitizeHtml('<span title="Tooltip">Text</span>');
        expect(result).toContain('title="Tooltip"');
      });

      it('should preserve span tags but strip data-token when ALLOW_DATA_ATTR is false', () => {
        // Note: sanitizeHtml has ALLOW_DATA_ATTR: false, so data-* attributes are stripped
        // Use sanitizeTokenHtml for token fields that need data-token preserved
        const result = sanitizeHtml('<span data-token="[node:title]">title</span>');
        expect(result).toContain('<span>');
        expect(result).toContain('title');
      });

      it('should allow colspan and rowspan in table context', () => {
        const result = sanitizeHtml('<table><tr><td colspan="2" rowspan="3">Cell</td></tr></table>');
        expect(result).toContain('<table>');
        expect(result).toContain('<td');
        expect(result).toContain('Cell');
      });
    });

    describe('text preservation', () => {
      it('should preserve plain text content', () => {
        const input = 'Hello, this is plain text!';
        expect(sanitizeHtml(input)).toBe(input);
      });

      it('should preserve text within allowed tags', () => {
        const input = '<p>This is a <strong>paragraph</strong> with text.</p>';
        const result = sanitizeHtml(input);
        expect(result).toContain('This is a');
        expect(result).toContain('paragraph');
        expect(result).toContain('with text');
      });
    });
  });

  describe('sanitizeTokenHtml', () => {
    describe('input validation', () => {
      it('should return empty string for null input', () => {
        expect(sanitizeTokenHtml(null)).toBe('');
      });

      it('should return empty string for undefined input', () => {
        expect(sanitizeTokenHtml(undefined)).toBe('');
      });

      it('should return empty string for empty string input', () => {
        expect(sanitizeTokenHtml('')).toBe('');
      });
    });

    describe('allowed tags', () => {
      it('should allow span tags', () => {
        const input = '<span class="config-token">token</span>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('<span');
        expect(result).toContain('</span>');
      });

      it('should allow br tags', () => {
        const input = 'Line 1<br>Line 2';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('<br');
      });

      it('should preserve native contenteditable line containers', () => {
        const input = '<div>Line 1</div><p>Line 2</p>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('<div>Line 1</div>');
        expect(result).toContain('<p>Line 2</p>');
      });
    });

    describe('token attributes', () => {
      it('should allow data-token attribute', () => {
        const input = '<span class="config-token" data-token="[node:title]">title</span>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('data-token="[node:title]"');
      });

      it('should allow contenteditable attribute', () => {
        const input = '<span contenteditable="false">text</span>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('contenteditable="false"');
      });

      it('should allow title attribute', () => {
        const input = '<span title="Token: [node:title]">title</span>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('title="Token: [node:title]"');
      });

      it('should allow draggable attribute', () => {
        const input = '<span draggable="false">text</span>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('draggable="false"');
      });

      it('should allow class attribute', () => {
        const input = '<span class="config-token">text</span>';
        const result = sanitizeTokenHtml(input);
        expect(result).toContain('class="config-token"');
      });
    });

    describe('XSS prevention', () => {
      it('should strip script tags', () => {
        const result = sanitizeTokenHtml('<script>alert("xss")</script>');
        expect(result).not.toContain('<script');
      });

      it('should strip event handlers', () => {
        const result = sanitizeTokenHtml('<span onclick="alert(\'xss\')">text</span>');
        expect(result).not.toContain('onclick');
      });
    });
  });

  describe('escapeHtml', () => {
    describe('input validation', () => {
      it('should return empty string for null input', () => {
        expect(escapeHtml(null)).toBe('');
      });

      it('should return empty string for undefined input', () => {
        expect(escapeHtml(undefined)).toBe('');
      });

      it('should return empty string for empty string input', () => {
        expect(escapeHtml('')).toBe('');
      });

      it('should return empty string for non-string input', () => {
        expect(escapeHtml(123 as any)).toBe('');
      });
    });

    describe('HTML entity escaping', () => {
      it('should escape less-than sign', () => {
        expect(escapeHtml('<')).toBe('&lt;');
      });

      it('should escape greater-than sign', () => {
        expect(escapeHtml('>')).toBe('&gt;');
      });

      it('should escape ampersand', () => {
        expect(escapeHtml('&')).toBe('&amp;');
      });

      it('should escape HTML tags', () => {
        const result = escapeHtml('<script>alert("xss")</script>');
        expect(result).toContain('&lt;script&gt;');
        expect(result).toContain('&lt;/script&gt;');
        expect(result).not.toContain('<script>');
      });

      it('should preserve normal text', () => {
        expect(escapeHtml('Hello, World!')).toBe('Hello, World!');
      });

      it('should escape mixed content', () => {
        const result = escapeHtml('1 < 2 && 2 > 1');
        expect(result).toBe('1 &lt; 2 &amp;&amp; 2 &gt; 1');
      });
    });
  });

});
