/**
 * Utility helpers for checking modeler permissions.
 *
 * Permissions are provided by the Drupal backend via
 * `drupalSettings.modeler_api.permissions`.  Missing keys default to `true`
 * (i.e. everything is allowed unless explicitly denied).
 */

import type { Settings, ModelerPermissions } from '../types/settings';

/**
 * Check whether a specific permission is granted.
 *
 * @param settings - The top-level Settings object passed through the app.
 * @param key      - The permission key to check (e.g. `'edit metadata'`).
 * @returns `true` when the permission is granted (or not specified).
 */
export function hasPermission(
  settings: Settings | undefined,
  key: keyof ModelerPermissions,
): boolean {
  const value = settings?.modeler_api?.permissions?.[key];
  // Treat missing / undefined as "allowed"
  return value !== false;
}
