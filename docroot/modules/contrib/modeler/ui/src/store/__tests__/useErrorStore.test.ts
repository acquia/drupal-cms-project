/**
 * Tests for useErrorStore — error log CRUD and dismiss.
 */

import { useErrorStore } from '../useErrorStore';
import type { ErrorRecord } from '../../utils/errorReporting';

const makeError = (id: string): ErrorRecord => ({
  id,
  timestamp: new Date().toISOString(),
  severity: 'error' as const,
  source: 'test',
  message: `Error ${id}`,
  count: 1,
  dismissed: false,
  recoveryAttempted: false,
  recoverySucceeded: false,
});

describe('useErrorStore', () => {
  beforeEach(() => {
    useErrorStore.setState({ errorLog: [] });
  });

  describe('initial state', () => {
    it('should start with an empty error log', () => {
      expect(useErrorStore.getState().errorLog).toEqual([]);
    });
  });

  describe('addError', () => {
    it('should append an error record', () => {
      useErrorStore.getState().addError(makeError('e1'));
      useErrorStore.getState().addError(makeError('e2'));
      expect(useErrorStore.getState().errorLog).toHaveLength(2);
      expect(useErrorStore.getState().errorLog[0].id).toBe('e1');
      expect(useErrorStore.getState().errorLog[1].id).toBe('e2');
    });
  });

  describe('dismissError', () => {
    it('should mark the matching record as dismissed', () => {
      useErrorStore.getState().addError(makeError('e1'));
      useErrorStore.getState().addError(makeError('e2'));

      useErrorStore.getState().dismissError('e1');

      const log = useErrorStore.getState().errorLog;
      expect(log[0].dismissed).toBe(true);
      expect(log[1].dismissed).toBe(false);
    });

    it('should not modify records that do not match', () => {
      useErrorStore.getState().addError(makeError('e1'));
      useErrorStore.getState().dismissError('nonexistent');
      expect(useErrorStore.getState().errorLog[0].dismissed).toBe(false);
    });
  });

  describe('clearErrors', () => {
    it('should empty the error log', () => {
      useErrorStore.getState().addError(makeError('e1'));
      useErrorStore.getState().addError(makeError('e2'));

      useErrorStore.getState().clearErrors();
      expect(useErrorStore.getState().errorLog).toEqual([]);
    });
  });
});
