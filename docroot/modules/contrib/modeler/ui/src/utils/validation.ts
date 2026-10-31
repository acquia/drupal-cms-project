/**
 * Response validation utilities for external API data.
 *
 * All validators follow the same pattern: they accept `unknown` data and
 * return the narrowed type on success, or throw a descriptive `Error` on
 * failure.  Callers can use the light-weight `is*` type-guards when they
 * only need a boolean check without throwing.
 */

import { t } from './translation';

// ---------------------------------------------------------------------------
// Safe JSON parsing
// ---------------------------------------------------------------------------

/**
 * Parse a JSON string while defending against prototype-pollution attacks.
 *
 * Untrusted JSON (e.g. clipboard payloads, drag-and-drop data, backend model
 * strings) can contain keys such as `__proto__`, `constructor`, or `prototype`
 * that, when assigned during parsing, can poison the prototype chain of the
 * resulting object. This helper uses a `JSON.parse` reviver that drops those
 * dangerous keys at every level of the object graph, so the parsed value can
 * never inject properties onto `Object.prototype`.
 *
 * The reviver returning `undefined` for a key causes `JSON.parse` to omit that
 * property entirely, which neutralizes the pollution vector without otherwise
 * altering the data.
 *
 * @typeParam T - The expected shape of the parsed value. Defaults to `unknown`
 *   so callers must narrow the result explicitly; callers that already validate
 *   the shape elsewhere may pass a concrete type via the generic.
 * @param text - The JSON string to parse.
 * @returns The parsed value, stripped of dangerous prototype-polluting keys.
 * @throws {SyntaxError} If `text` is not valid JSON (same as `JSON.parse`).
 */
export function safeJsonParse<T = unknown>(text: string): T {
  return JSON.parse(text, (key, value) =>
    key === '__proto__' || key === 'constructor' || key === 'prototype'
      ? undefined
      : value,
  ) as T;
}

// ---------------------------------------------------------------------------
// CSRF Token
// ---------------------------------------------------------------------------

/**
 * Validate a CSRF token response.
 *
 * The token endpoint returns a plain-text string.  We verify it is a
 * non-empty string that looks like a plausible token (printable ASCII,
 * reasonable length) and does NOT look like an HTML error page.
 */
export function validateCsrfToken(response: Response, token: string): string {
  if (!response.ok) {
    throw new Error(
      t('CSRF token request failed: @status @text', {
        '@status': String(response.status),
        '@text': response.statusText,
      }),
    );
  }

  const trimmed = token.trim();

  if (trimmed.length === 0) {
    throw new Error(t('CSRF token response was empty'));
  }

  // Drupal tokens are typically 43-character base64 strings but we use a
  // generous upper bound.  If the response looks like HTML it is almost
  // certainly an error page.
  if (trimmed.startsWith('<') || trimmed.startsWith('<!')) {
    throw new Error(t('CSRF token response contained HTML instead of a token'));
  }

  if (trimmed.length > 512) {
    throw new Error(t('CSRF token response was unexpectedly large'));
  }

  return trimmed;
}

/**
 * Fetch a CSRF token from `tokenUrl` with validation.
 *
 * Shared helper used by hooks that need a token before making API calls.
 * Accepts an optional `AbortSignal` for cancellation support.
 */
export async function fetchValidatedCsrfToken(
  tokenUrl: string,
  signal?: AbortSignal,
): Promise<string> {
  const response = await fetch(tokenUrl, signal ? { signal } : undefined);
  const text = await response.text();
  return validateCsrfToken(response, text);
}

// ---------------------------------------------------------------------------
// Replay Entries
// ---------------------------------------------------------------------------

/** Minimal shape of a single replay entry coming from the backend. */
interface ValidReplayEntry {
  model_id: string;
  component_id: string;
  history: unknown[];
  timestamp: string | number;
  user: string | { name?: string; [key: string]: unknown };
  ip: string;
  url: string;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Normalize a raw replay entry from the backend.
 *
 * Accepts both the legacy field names (`eca_id`, `event_id`) and the
 * current generic names (`model_id`, `component_id`) so that the modeler
 * works with backends that haven't been updated yet.
 */
function normalizeReplayEntry(entry: Record<string, unknown>): Record<string, unknown> {
  const normalized = { ...entry };
  // Accept legacy `eca_id` → `model_id`
  if (typeof normalized.eca_id === 'string' && typeof normalized.model_id !== 'string') {
    normalized.model_id = normalized.eca_id;
  }
  // Accept legacy `event_id` → `component_id`
  if (typeof normalized.event_id === 'string' && typeof normalized.component_id !== 'string') {
    normalized.component_id = normalized.event_id;
  }
  return normalized;
}

/**
 * Type-guard for a single replay entry.
 */
function isValidReplayEntry(entry: unknown): entry is ValidReplayEntry {
  if (!isRecord(entry)) return false;

  const normalized = normalizeReplayEntry(entry);
  if (typeof normalized.model_id !== 'string') return false;
  if (typeof normalized.component_id !== 'string') return false;
  if (!Array.isArray(normalized.history)) return false;
  if (typeof normalized.timestamp !== 'string' && typeof normalized.timestamp !== 'number') return false;
  if (typeof normalized.user !== 'string' && !isRecord(normalized.user)) return false;
  if (typeof normalized.ip !== 'string') return false;
  if (typeof normalized.url !== 'string') return false;

  return true;
}

/**
 * Validate the full replay-entries API response.
 *
 * Expects a JSON array where every element conforms to `ValidReplayEntry`.
 * Returns only the entries that pass validation (with a console warning for
 * any that are dropped).
 */
export function validateReplayEntries(data: unknown): ValidReplayEntry[] {
  if (!Array.isArray(data)) {
    throw new Error(t('Unexpected response format'));
  }

  if (data.length === 0) {
    return [];
  }

  const valid: ValidReplayEntry[] = [];
  const invalidIndices: number[] = [];

  data.forEach((entry: unknown, index: number) => {
    if (isValidReplayEntry(entry)) {
      // Normalize legacy field names before storing
      const normalized = normalizeReplayEntry(entry as unknown as Record<string, unknown>);
      valid.push(normalized as unknown as ValidReplayEntry);
    } else {
      invalidIndices.push(index);
    }
  });

  if (invalidIndices.length > 0) {
    console.warn(
      `Dropped ${invalidIndices.length} invalid replay entries at indices: ${invalidIndices.join(', ')}`,
    );
  }

  return valid;
}

// ---------------------------------------------------------------------------
// Configuration Form (JSON response)
// ---------------------------------------------------------------------------

/** Result of validating a configuration response. */
export interface ConfigurationResponseResult {
  form: Record<string, unknown>[] | null;
  error: string | null;
}

/**
 * Validate a configuration form JSON response.
 *
 * The backend returns a JSON object with either:
 * - `{ form: [ { key, type, ... }, ... ] }` – an array of form field objects
 * - `{ error: "..." }` – an error message
 * - `{}` – empty, meaning the component has no configuration form
 *
 * Returns a `ConfigurationResponseResult` with the form data and/or error.
 */
export function validateConfigurationResponse(data: unknown): ConfigurationResponseResult {
  // Empty / falsy responses are valid – component has no configuration.
  if (data === null || data === undefined) {
    return { form: null, error: null };
  }

  if (!isRecord(data)) {
    throw new Error(t('Configuration response is not an object'));
  }

  const error = typeof data.error === 'string' ? data.error : null;

  if (!data.form) {
    return { form: null, error };
  }

  if (!Array.isArray(data.form)) {
    console.warn('Configuration response "form" property is not an array');
    return { form: null, error };
  }

  return { form: data.form as Record<string, unknown>[], error };
}

// ---------------------------------------------------------------------------
// Model Data
// ---------------------------------------------------------------------------

/**
 * Validate the top-level shape of model data before it is passed to
 * `parseModelData`.
 *
 * Returns an array of warning strings. An empty array means the data is
 * structurally valid.  This replaces the existing (unused)
 * `validateModelData` in modelUtils.ts with a version that is actually
 * called during loading.
 */
export function validateModelDataShape(data: unknown): string[] {
  const warnings: string[] = [];

  if (!data) {
    warnings.push(t('Model data is empty'));
    return warnings;
  }

  if (!isRecord(data)) {
    warnings.push(t('Model data is not an object'));
    return warnings;
  }

  // Nodes
  if (data.nodes !== undefined) {
    if (!Array.isArray(data.nodes)) {
      warnings.push(t('Model data "nodes" is not an array'));
    } else {
      data.nodes.forEach((node: unknown, i: number) => {
        if (!isRecord(node) || typeof node.id !== 'string') {
          warnings.push(t('Invalid node at index @index: missing or non-string id', { '@index': String(i) }));
        }
      });
    }
  }

  // Edges
  if (data.edges !== undefined) {
    if (!Array.isArray(data.edges)) {
      warnings.push(t('Model data "edges" is not an array'));
    } else {
      data.edges.forEach((edge: unknown, i: number) => {
        if (!isRecord(edge)) {
          warnings.push(t('Invalid edge at index @index: not an object', { '@index': String(i) }));
        } else {
          if (typeof edge.source !== 'string') {
            warnings.push(t('Invalid edge at index @index: missing or non-string source', { '@index': String(i) }));
          }
          if (typeof edge.target !== 'string') {
            warnings.push(t('Invalid edge at index @index: missing or non-string target', { '@index': String(i) }));
          }
        }
      });
    }
  }

  // Orphaned edges (edges referencing non-existent nodes)
  if (Array.isArray(data.nodes) && Array.isArray(data.edges)) {
    const nodeIds = new Set(
      (data.nodes as unknown[])
        .filter((n): n is Record<string, unknown> => isRecord(n) && typeof n.id === 'string')
        .map((n) => n.id as string),
    );
    (data.edges as unknown[]).forEach((edge: unknown, i: number) => {
      if (isRecord(edge)) {
        if (typeof edge.source === 'string' && !nodeIds.has(edge.source)) {
          warnings.push(t('Edge at index @index references non-existent source: @source', { '@index': String(i), '@source': edge.source }));
        }
        if (typeof edge.target === 'string' && !nodeIds.has(edge.target)) {
          warnings.push(t('Edge at index @index references non-existent target: @target', { '@index': String(i), '@target': edge.target }));
        }
      }
    });
  }

  return warnings;
}

// ---------------------------------------------------------------------------
// Documentation Response
// ---------------------------------------------------------------------------

/**
 * Validate a documentation fetch response before attempting to parse it.
 *
 * Checks the HTTP status and optionally verifies the Content-Type header
 * indicates HTML.
 */
export function validateDocumentationResponse(response: Response): void {
  if (!response.ok) {
    throw new Error(
      t('Failed to fetch documentation: @status @text', {
        '@status': String(response.status),
        '@text': response.statusText,
      }),
    );
  }

  const contentType = response.headers.get('content-type') || '';
  if (contentType && !contentType.includes('text/html') && !contentType.includes('text/plain') && !contentType.includes('application/xhtml')) {
    console.warn(
      `Documentation response has unexpected Content-Type: ${contentType}`,
    );
  }
}
