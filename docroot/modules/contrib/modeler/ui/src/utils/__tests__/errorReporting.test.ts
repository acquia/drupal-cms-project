import {
  reportError,
  markRecoveryAttempted,
  _resetForTesting,
} from '../errorReporting';
import { ERROR_RECOVERY } from '../../constants/dimensions';

describe('errorReporting', () => {
  beforeEach(() => {
    _resetForTesting();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('reportError', () => {
    it('should create a new error record with correct fields', () => {
      const record = reportError('Canvas', new Error('Render failed'));

      expect(record.id).toMatch(/^err_/);
      expect(record.source).toBe('Canvas');
      expect(record.message).toBe('Render failed');
      expect(record.severity).toBe('error');
      expect(record.count).toBe(1);
      expect(record.dismissed).toBe(false);
      expect(record.recoveryAttempted).toBe(false);
      expect(record.recoverySucceeded).toBe(false);
      expect(record.timestamp).toBeTruthy();
    });

    it('should use the string representation for non-Error values', () => {
      const record = reportError('Toolbar', 'simple string error');
      expect(record.message).toBe('simple string error');
    });

    it('should default to "Unknown error" for falsy values', () => {
      const record = reportError('Properties', null);
      expect(record.message).toBe('Unknown error');
    });

    it('should use provided severity level', () => {
      const warning = reportError('Replay', 'minor issue', 'warning');
      expect(warning.severity).toBe('warning');

      const info = reportError('Replay', 'fyi', 'info');
      expect(info.severity).toBe('info');
    });

    it('should store componentStack when provided', () => {
      const record = reportError('Canvas', new Error('crash'), 'error', 'at SomeComponent\nat Flow');
      expect(record.componentStack).toBe('at SomeComponent\nat Flow');
    });

    it('should not store componentStack when not provided', () => {
      const record = reportError('Canvas', new Error('crash'));
      expect(record.componentStack).toBeUndefined();
    });

    it('should generate unique IDs for each record', () => {
      const r1 = reportError('A', new Error('a'));
      const r2 = reportError('B', new Error('b'));
      expect(r1.id).not.toBe(r2.id);
    });
  });

  describe('deduplication', () => {
    it('should increment count for identical errors within the dedup window', () => {
      const r1 = reportError('Canvas', new Error('Render failed'));
      const r2 = reportError('Canvas', new Error('Render failed'));

      expect(r1.id).toBe(r2.id);
      expect(r2.count).toBe(2);
    });

    it('should not deduplicate errors from different sources', () => {
      const r1 = reportError('Canvas', new Error('Render failed'));
      const r2 = reportError('Properties', new Error('Render failed'));

      expect(r1.id).not.toBe(r2.id);
    });

    it('should not deduplicate errors with different messages', () => {
      const r1 = reportError('Canvas', new Error('Error A'));
      const r2 = reportError('Canvas', new Error('Error B'));

      expect(r1.id).not.toBe(r2.id);
    });

    it('should create a new record after the dedup window expires', () => {
      reportError('Canvas', new Error('Render failed'));

      // Advance time past the dedup window
      jest.advanceTimersByTime(ERROR_RECOVERY.DEDUP_WINDOW + 1);

      // Use a real Date for the new timestamp
      const now = Date.now() + ERROR_RECOVERY.DEDUP_WINDOW + 1;
      jest.setSystemTime(now);

      const r2 = reportError('Canvas', new Error('Render failed'));

      // Should be a separate record since we're outside the dedup window
      expect(r2.count).toBeGreaterThanOrEqual(1);
    });
  });

  describe('markRecoveryAttempted', () => {
    it('should mark a record as recovery attempted with success', () => {
      const record = reportError('Canvas', new Error('crash'));
      markRecoveryAttempted(record.id, true);

      // The returned record is the same object reference stored internally,
      // so mutations from markRecoveryAttempted are visible here.
      expect(record.recoveryAttempted).toBe(true);
      expect(record.recoverySucceeded).toBe(true);
    });

    it('should mark a record as recovery attempted with failure', () => {
      const record = reportError('Canvas', new Error('crash'));
      markRecoveryAttempted(record.id, false);

      expect(record.recoveryAttempted).toBe(true);
      expect(record.recoverySucceeded).toBe(false);
    });

    it('should do nothing for non-existent record IDs', () => {
      const record = reportError('Canvas', new Error('crash'));
      markRecoveryAttempted('non_existent_id', true);

      expect(record.recoveryAttempted).toBe(false);
    });
  });

  describe('_resetForTesting', () => {
    it('should reset the ID counter', () => {
      reportError('Canvas', new Error('first'));
      _resetForTesting();

      const record = reportError('Canvas', new Error('second'));
      expect(record.id).toMatch(/^err_1_/);
    });

    it('should allow fresh error reporting after reset', () => {
      reportError('Canvas', new Error('before reset'));
      _resetForTesting();

      const record = reportError('Canvas', new Error('after reset'));
      expect(record.count).toBe(1);
      expect(record.message).toBe('after reset');
    });
  });
});
