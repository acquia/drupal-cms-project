/**
 * Tests for tokenUtils
 */

import {
  convertTokensToHTML,
  convertHTMLToTokens,
  createTokenElement,
  isTokenElement,
  parseTokenFromDragEvent,
} from '../tokenUtils';

describe('tokenUtils', () => {
  describe('convertTokensToHTML', () => {
    it('should return empty string for null input', () => {
      expect(convertTokensToHTML(null)).toBe('');
    });

    it('should return empty string for undefined input', () => {
      expect(convertTokensToHTML(undefined as any)).toBe('');
    });

    it('should return same string if no tokens present', () => {
      expect(convertTokensToHTML('Hello world')).toBe('Hello world');
    });

    it('should convert simple token to HTML span', () => {
      const result = convertTokensToHTML('[user:name]');
      expect(result).toContain('class="config-token"');
      expect(result).toContain('data-token="[user:name]"');
      expect(result).toContain('>name</span>');
    });

    it('should use last path segment as label', () => {
      const result = convertTokensToHTML('[node:author:mail]');
      expect(result).toContain('>mail</span>');
    });

    it('should convert multiple tokens', () => {
      const result = convertTokensToHTML('Hello [user:name], your email is [user:mail]');
      expect(result).toContain('data-token="[user:name]"');
      expect(result).toContain('data-token="[user:mail]"');
    });

    it('should preserve text around tokens', () => {
      const result = convertTokensToHTML('Hello [user:name]!');
      expect(result).toMatch(/^Hello.*!$/);
    });

    it('should handle token at start of string', () => {
      const result = convertTokensToHTML('[user:name] is logged in');
      expect(result).toMatch(/^<span/);
    });

    it('should handle token at end of string', () => {
      const result = convertTokensToHTML('Welcome [user:name]');
      expect(result).toMatch(/<\/span>$/);
    });

    it('should set contenteditable to false on token spans', () => {
      const result = convertTokensToHTML('[token]');
      expect(result).toContain('contenteditable="false"');
    });
  });

  describe('convertHTMLToTokens', () => {
    it('should return empty string for null input', () => {
      expect(convertHTMLToTokens(null)).toBe('');
    });

    it('should return empty string for undefined input', () => {
      expect(convertHTMLToTokens(undefined as any)).toBe('');
    });

    it('should return same string if no token spans present', () => {
      expect(convertHTMLToTokens('Hello world')).toBe('Hello world');
    });

    it('should convert token span back to token string', () => {
      const html = '<span class="config-token" data-token="[user:name]">name</span>';
      expect(convertHTMLToTokens(html)).toBe('[user:name]');
    });

    it('should preserve text around tokens', () => {
      const html = 'Hello <span class="config-token" data-token="[user:name]">name</span>!';
      expect(convertHTMLToTokens(html)).toBe('Hello [user:name]!');
    });

    it('should convert multiple token spans', () => {
      const html = '<span class="config-token" data-token="[user:name]">name</span> - <span class="config-token" data-token="[user:mail]">mail</span>';
      expect(convertHTMLToTokens(html)).toBe('[user:name] - [user:mail]');
    });

    it('should handle nested HTML structure', () => {
      const html = '<div>Hello <span class="config-token" data-token="[user:name]">name</span></div>';
      expect(convertHTMLToTokens(html)).toBe('Hello [user:name]');
    });

    it('preserves native contenteditable block boundaries as newlines', () => {
      const token = '<span class="config-token" data-token="[site:name]">name</span>';
      expect(convertHTMLToTokens(`line1<div>${token}</div>`)).toBe('line1\n[site:name]');
      expect(convertHTMLToTokens('<div>line1</div><div>X</div>')).toBe('line1\nX');
      expect(convertHTMLToTokens('line1<div><br></div>')).toBe('line1\n');
      expect(convertHTMLToTokens('<div>line1</div><div><br></div><div>X</div>'))
        .toBe('line1\n\nX');
    });

    it('strips the trailing zero-width-space caret spacer after a trailing token (Issue B)', () => {
      // A field ending in a token gets a ZWSP (\u200B) appended as a caret spot;
      // it must NOT appear in the serialized value.
      const html = 'Hello <span class="config-token" data-token="[user:name]">name</span>\u200B';
      const out = convertHTMLToTokens(html);
      expect(out).toBe('Hello [user:name]');
      expect(out).not.toContain('\u200B');
    });

    it('strips zero-width-spaces wherever they appear', () => {
      const html = '\u200B<span class="config-token" data-token="[a:b]">b</span>\u200Btext\u200B';
      const out = convertHTMLToTokens(html);
      expect(out).toBe('[a:b]text');
      expect(out).not.toContain('\u200B');
    });
  });

  describe('round-trip conversion', () => {
    it('should preserve simple text', () => {
      const text = 'Hello world';
      expect(convertHTMLToTokens(convertTokensToHTML(text))).toBe(text);
    });

    it('should preserve text with token', () => {
      const text = 'Hello [user:name]!';
      expect(convertHTMLToTokens(convertTokensToHTML(text))).toBe(text);
    });

    it('should preserve multiple tokens', () => {
      const text = '[user:name] ([user:mail])';
      expect(convertHTMLToTokens(convertTokensToHTML(text))).toBe(text);
    });
  });

  describe('createTokenElement', () => {
    it('should create a span element', () => {
      const element = createTokenElement('name', '[user:name]');
      expect(element.tagName).toBe('SPAN');
    });

    it('should set config-token class', () => {
      const element = createTokenElement('name', '[user:name]');
      expect(element.className).toBe('config-token');
    });

    it('should set label as text content', () => {
      const element = createTokenElement('display name', '[user:name]');
      expect(element.textContent).toBe('display name');
    });

    it('should set data-token attribute', () => {
      const element = createTokenElement('name', '[user:name]');
      expect(element.getAttribute('data-token')).toBe('[user:name]');
    });

    it('should wrap token in brackets if not present', () => {
      const element = createTokenElement('name', 'user:name');
      expect(element.getAttribute('data-token')).toBe('[user:name]');
    });

    it('should not double-wrap brackets', () => {
      const element = createTokenElement('name', '[user:name]');
      expect(element.getAttribute('data-token')).toBe('[user:name]');
    });

    it('should set contenteditable to false', () => {
      const element = createTokenElement('name', '[token]');
      expect(element.getAttribute('contenteditable')).toBe('false');
    });

    it('should set draggable to true', () => {
      const element = createTokenElement('name', '[token]');
      expect(element.getAttribute('draggable')).toBe('true');
    });

    it('should set title with token info', () => {
      const element = createTokenElement('name', '[user:name]');
      expect(element.getAttribute('title')).toContain('[user:name]');
    });
  });

  describe('isTokenElement', () => {
    it('should return false for null', () => {
      expect(isTokenElement(null)).toBe(false);
    });

    it('should return false for text node', () => {
      const textNode = document.createTextNode('hello');
      expect(isTokenElement(textNode)).toBe(false);
    });

    it('should return false for element without config-token class', () => {
      const div = document.createElement('div');
      expect(isTokenElement(div)).toBe(false);
    });

    it('should return true for element with config-token class', () => {
      const span = document.createElement('span');
      span.className = 'config-token';
      expect(isTokenElement(span)).toBe(true);
    });

    it('should return true for element with multiple classes including config-token', () => {
      const span = document.createElement('span');
      span.className = 'other-class config-token another-class';
      expect(isTokenElement(span)).toBe(true);
    });
  });

  describe('parseTokenFromDragEvent', () => {
    const createMockDataTransfer = (data: Record<string, string>): DataTransfer => {
      return {
        getData: (type: string) => data[type] || '',
      } as DataTransfer;
    };

    // Mock console methods for tests that trigger warnings/errors
    let consoleWarnSpy: jest.SpyInstance;
    let consoleErrorSpy: jest.SpyInstance;

    beforeEach(() => {
      consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
      consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
      consoleWarnSpy.mockRestore();
      consoleErrorSpy.mockRestore();
    });

    it('should return null if no token data', () => {
      const dataTransfer = createMockDataTransfer({});
      expect(parseTokenFromDragEvent(dataTransfer)).toBeNull();
    });

    it('should return null for invalid JSON', () => {
      const dataTransfer = createMockDataTransfer({
        'application/token': 'not json',
      });
      expect(parseTokenFromDragEvent(dataTransfer)).toBeNull();
      expect(consoleErrorSpy).toHaveBeenCalledWith(
        'Failed to parse token data:',
        expect.any(SyntaxError)
      );
    });

    it('should return null if label is missing', () => {
      const dataTransfer = createMockDataTransfer({
        'application/token': JSON.stringify({ token: '[user:name]' }),
      });
      expect(parseTokenFromDragEvent(dataTransfer)).toBeNull();
      expect(consoleWarnSpy).toHaveBeenCalledWith('Invalid token data: missing label or token');
    });

    it('should return null if token is missing', () => {
      const dataTransfer = createMockDataTransfer({
        'application/token': JSON.stringify({ label: 'name' }),
      });
      expect(parseTokenFromDragEvent(dataTransfer)).toBeNull();
      expect(consoleWarnSpy).toHaveBeenCalledWith('Invalid token data: missing label or token');
    });

    it('should return parsed token data', () => {
      const dataTransfer = createMockDataTransfer({
        'application/token': JSON.stringify({ label: 'User Name', token: '[user:name]' }),
      });
      const result = parseTokenFromDragEvent(dataTransfer);
      expect(result).toEqual({ label: 'User Name', token: '[user:name]' });
    });

    it('should return null for non-string label', () => {
      const dataTransfer = createMockDataTransfer({
        'application/token': JSON.stringify({ label: 123, token: '[token]' }),
      });
      expect(parseTokenFromDragEvent(dataTransfer)).toBeNull();
      expect(consoleWarnSpy).toHaveBeenCalledWith('Invalid token data: missing label or token');
    });

    it('should return null for non-string token', () => {
      const dataTransfer = createMockDataTransfer({
        'application/token': JSON.stringify({ label: 'name', token: 123 }),
      });
      expect(parseTokenFromDragEvent(dataTransfer)).toBeNull();
      expect(consoleWarnSpy).toHaveBeenCalledWith('Invalid token data: missing label or token');
    });
  });
});
