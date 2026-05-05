// eslint-disable-next-line import/no-extraneous-dependencies
import { findAttributeRange } from 'ckeditor5/src/typing';

/**
 * Returns a link range based on selection from
 *  href attribute at selection's first position.
 */
export function getCurrentLinkRange(model, selection, hrefSourceValue) {
  const position = selection.getFirstPosition();
  const range = findAttributeRange(position, 'linkHref', hrefSourceValue, model);
  return range;
};

/**
 * Returns a text of a link range.
 *
 * If the returned value is `undefined`, the range contains elements other than text nodes.
 */
export function extractTextFromLinkRange(range) {
  let text = '';
  for (const item of range.getItems()) {
    if (!item.is('$text') && !item.is('$textProxy')) {
      return;
    }
    text += item.data;
  }
  return text;
}

/**
 * Returns the major version number from a semantic version string.
 * Examples:
 *  - "45.0.0" -> 45
 *  - "45" -> 45
 *  - "v45.2.1" -> 45
 *  - "  45.0.0-alpha  " -> 45
 *  - "abc" -> null
 *
 * @param {string|number} version
 * @returns {number|null} Major version number, or null if not found
 */
export function getMajorVersion(version) {
  if (version === null || version === undefined) return null;

  // Normalize input to string and trim whitespace
  const str = String(version).trim();

  // Take the part before the first full stop
  const firstSegment = str.split('.')[0];

  // Extract leading digits from the first segment (handles "v45", "45", etc.)
  const match = firstSegment.match(/\d+/);

  return match ? Number(match[0]) : null;
}
