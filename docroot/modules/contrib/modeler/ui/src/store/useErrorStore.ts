import { create } from 'zustand';
import type { ErrorRecord } from '../utils/errorReporting';
import { ERROR_RECOVERY } from '../constants/dimensions';

/** Maximum number of error records retained in the log to prevent unbounded growth. */
const MAX_ERROR_LOG = ERROR_RECOVERY.MAX_ERROR_LOG_SIZE;

interface ErrorState {
  errorLog: ErrorRecord[];
  addError: (record: ErrorRecord) => void;
  dismissError: (recordId: string) => void;
  clearErrors: () => void;
}

export const useErrorStore = create<ErrorState>((set) => ({
  errorLog: [],
  addError: (record) =>
    set((state) => ({
      errorLog: [...state.errorLog, record].slice(-MAX_ERROR_LOG),
    })),
  dismissError: (recordId) =>
    set((state) => ({
      errorLog: state.errorLog.map((r) => (r.id === recordId ? { ...r, dismissed: true } : r)),
    })),
  clearErrors: () => set({ errorLog: [] }),
}));
