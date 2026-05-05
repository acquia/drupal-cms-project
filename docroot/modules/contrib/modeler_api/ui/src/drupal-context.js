/**
 * @file
 * Shared module for holding a stable reference to the Drupal object.
 *
 * The Drupal global is captured once during initialization via the IIFE
 * closure in index.js and stored here. Other modules import from this
 * file to get the captured reference, avoiding issues where
 * window.Drupal becomes unavailable after AJAX-driven page rebuilds.
 */

/**
 * The stored Drupal reference.
 *
 * @type {Object|null}
 */
let drupal = null;

/**
 * Stores the Drupal reference for use by other modules.
 *
 * @param {Object} ref - The Drupal global object.
 */
export function setDrupal(ref) {
  drupal = ref;
}

/**
 * Returns the stored Drupal reference.
 *
 * @returns {Object|null} The Drupal object, or null if not yet set.
 */
export function getDrupal() {
  return drupal;
}
