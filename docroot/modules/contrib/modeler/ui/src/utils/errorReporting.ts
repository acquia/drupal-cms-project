/**
 * Centralized error reporting utility for the workflow modeler.
 *
 * Provides:
 * - A typed error log with severity levels and source context
 * - Automatic deduplication of identical errors within a time window
 * - Size-limited log with oldest-first eviction
 * - Subscriber pattern so the store (or any listener) can react to new errors
 * - Helper to format an ErrorRecord for display
 */
import { ERROR_RECOVERY } from '../constants/dimensions';

// ────────────────────────────────────────────────
// Types
// ────────────────────────────────────────────────

/** Severity levels for reported errors. */
type ErrorSeverity = 'error' | 'warning' | 'info';

/** A single recorded error. */
export interface ErrorRecord {
  /** Unique identifier for this record */
  id: string;
  /** ISO-8601 timestamp */
  timestamp: string;
  /** Severity level */
  severity: ErrorSeverity;
  /** Source of the error (panel name, hook name, utility name) */
  source: string;
  /** Human-readable error message */
  message: string;
  /** Number of times this exact error has occurred within the dedup window */
  count: number;
  /** Whether the user has dismissed this error from the log */
  dismissed: boolean;
  /** Optional component stack from React error boundaries */
  componentStack?: string;
  /** Whether auto-recovery was attempted */
  recoveryAttempted: boolean;
  /** Whether auto-recovery succeeded */
  recoverySucceeded: boolean;
}

/** Callback type for subscribers. */
type ErrorSubscriber = (record: ErrorRecord) => void;

// ────────────────────────────────────────────────
// Internal state
// ────────────────────────────────────────────────

let errorLog: ErrorRecord[] = [];
let nextId = 1;
const subscribers: Set<ErrorSubscriber> = new Set();

// ────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────

/** Generate a unique ID for an error record. */
function generateId(): string {
  return `err_${nextId++}_${Date.now()}`;
}

/**
 * Create a deduplication key from source + message so we can collapse
 * repeated identical errors.
 */
function dedupKey(source: string, message: string): string {
  return `${source}::${message}`;
}

// ────────────────────────────────────────────────
// Public API
// ────────────────────────────────────────────────

/**
 * Report an error to the centralized log.
 *
 * If the same source + message combination was reported within the dedup
 * window, the existing record's count is incremented instead of creating
 * a new entry.
 *
 * @returns The ErrorRecord that was created or updated.
 */
export function reportError(
  source: string,
  error: unknown,
  severity: ErrorSeverity = 'error',
  componentStack?: string,
): ErrorRecord {
  const message = error instanceof Error
    ? error.message
    : String(error || 'Unknown error');

  // Dedup check – look for an existing record with the same key within the
  // configured window.
  const key = dedupKey(source, message);
  const now = Date.now();
  const existing = errorLog.find(
    (r) =>
      dedupKey(r.source, r.message) === key &&
      now - new Date(r.timestamp).getTime() < ERROR_RECOVERY.DEDUP_WINDOW,
  );

  if (existing) {
    existing.count += 1;
    existing.timestamp = new Date().toISOString();
    // Notify subscribers about the updated record
    subscribers.forEach((fn) => fn(existing));
    return existing;
  }

  // Create a new record
  const record: ErrorRecord = {
    id: generateId(),
    timestamp: new Date().toISOString(),
    severity,
    source,
    message,
    count: 1,
    dismissed: false,
    componentStack,
    recoveryAttempted: false,
    recoverySucceeded: false,
  };

  errorLog.push(record);

  // Evict oldest entries if we exceed the maximum log size
  while (errorLog.length > ERROR_RECOVERY.MAX_ERROR_LOG_SIZE) {
    errorLog.shift();
  }

  // Notify subscribers
  subscribers.forEach((fn) => fn(record));

  return record;
}

/**
 * Mark a record as having had a recovery attempt.
 */
export function markRecoveryAttempted(recordId: string, succeeded: boolean): void {
  const record = errorLog.find((r) => r.id === recordId);
  if (record) {
    record.recoveryAttempted = true;
    record.recoverySucceeded = succeeded;
  }
}

/**
 * Reset internal state. Intended for testing only.
 */
export function _resetForTesting(): void {
  errorLog = [];
  nextId = 1;
  subscribers.clear();
}
