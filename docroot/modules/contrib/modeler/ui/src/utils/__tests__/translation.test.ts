import { t } from '../translation';

describe('translation utility', () => {
  describe('t() function', () => {
    it('should return the string unchanged when no args provided', () => {
      expect(t('Hello')).toBe('Hello');
    });

    it('should return empty string for empty input', () => {
      expect(t('')).toBe('');
    });

    it('should replace @-prefixed placeholders', () => {
      expect(t('Hello @name', { '@name': 'World' })).toBe('Hello World');
    });

    it('should replace multiple placeholders', () => {
      expect(t('@count items by @author', { '@count': 5, '@author': 'Alice' }))
        .toBe('5 items by Alice');
    });

    it('should replace all occurrences of the same placeholder', () => {
      expect(t('@name and @name again', { '@name': 'Bob' }))
        .toBe('Bob and Bob again');
    });

    it('should handle numeric values', () => {
      expect(t('Step @current of @total', { '@current': 3, '@total': 10 }))
        .toBe('Step 3 of 10');
    });

    it('should handle zero as a value', () => {
      expect(t('@count items', { '@count': 0 })).toBe('0 items');
    });

    it('should handle empty args object', () => {
      expect(t('Hello', {})).toBe('Hello');
    });

    it('should handle special regex characters in placeholders', () => {
      // The key has characters that need regex escaping
      expect(t('Value is @count', { '@count': 42 })).toBe('Value is 42');
    });

    it('should not modify string when placeholder not found', () => {
      expect(t('Hello @name', { '@other': 'World' })).toBe('Hello @name');
    });

    it('should handle Drupal.t when Drupal is available', () => {
      const originalDrupal = (global as any).Drupal;
      (global as any).Drupal = {
        t: jest.fn((str: string, _args: any) => `translated:${str}`),
      };

      expect(t('Save')).toBe('translated:Save');
      expect((global as any).Drupal.t).toHaveBeenCalledWith('Save', undefined);

      (global as any).Drupal = originalDrupal;
    });

    it('should pass args to Drupal.t when available', () => {
      const originalDrupal = (global as any).Drupal;
      const mockT = jest.fn((str: string) => str);
      (global as any).Drupal = { t: mockT };

      t('Hello @name', { '@name': 'World' });
      expect(mockT).toHaveBeenCalledWith('Hello @name', { '@name': 'World' });

      (global as any).Drupal = originalDrupal;
    });

    it('should handle Drupal object without t function', () => {
      const originalDrupal = (global as any).Drupal;
      (global as any).Drupal = {}; // No t function
      
      // Should fall through to manual replacement
      expect(t('Hello @name', { '@name': 'Test' })).toBe('Hello Test');
      
      (global as any).Drupal = originalDrupal;
    });

    it('should handle placeholder replacement with regex special characters in keys', () => {
      // Ensure regex special characters in keys are properly escaped
      expect(t('Value is @count+1', { '@count+1': '42' })).toBe('Value is 42');
    });

    it('should handle multiple different placeholder keys', () => {
      const result = t('@greeting @name, you have @count messages', {
        '@greeting': 'Hello',
        '@name': 'Alice',
        '@count': 5,
      });
      expect(result).toBe('Hello Alice, you have 5 messages');
    });

    it('should handle string with no placeholders but args provided', () => {
      expect(t('No placeholders here', { '@unused': 'value' })).toBe('No placeholders here');
    });

    it('should return original string when Drupal is undefined', () => {
      const originalDrupal = (global as any).Drupal;
      delete (global as any).Drupal;
      
      expect(t('Simple string')).toBe('Simple string');
      
      (global as any).Drupal = originalDrupal;
    });
  });
});
