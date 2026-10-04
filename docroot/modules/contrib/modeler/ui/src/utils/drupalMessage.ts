/**
 * Utility for showing Drupal messages from within React components and hooks.
 *
 * Centralizes the Drupal.Message integration so that individual hooks and
 * components don't need to duplicate the try/catch boilerplate.
 */

/**
 * Shows a Drupal message if Drupal.Message is available.
 * Falls back silently when not in Drupal context (tests, Storybook).
 */
export function showDrupalMessage(message: string, type: 'status' | 'warning' | 'error' = 'status'): void {
  if (typeof Drupal !== 'undefined' && Drupal.Message) {
    try {
      const messenger = new Drupal.Message();
      messenger.add(message, { type });
    } catch {
      // Silently fail if Drupal.Message is not available
    }
  }
}
