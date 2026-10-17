/**
 * Translation utility for Drupal integration
 * 
 * Wraps Drupal.t() to provide translations while maintaining
 * compatibility when running outside of Drupal context (e.g., tests, Storybook)
 */

/**
 * Translates a string using Drupal's translation system.
 * Falls back to returning the original string if Drupal is not available.
 * 
 * @param str - The string to translate
 * @param args - Optional placeholder replacements (e.g., { '@name': 'value' })
 * @returns The translated string
 * 
 * @example
 * // Simple translation
 * t('Save')
 * 
 * // With placeholder
 * t('Hello @name', { '@name': userName })
 * 
 * // With count
 * t('@count items selected', { '@count': selectedCount })
 */
export function t(str: string, args?: Record<string, string | number>): string {
  if (typeof Drupal !== 'undefined' && Drupal.t) {
    return Drupal.t(str, args);
  }
  
  // Fallback: replace placeholders manually when Drupal is not available
  if (args) {
    let result = str;
    for (const [key, value] of Object.entries(args)) {
      result = result.replace(new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), String(value));
    }
    return result;
  }
  
  return str;
}


