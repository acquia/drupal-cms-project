/**
 * PanelErrorBoundary - Granular error boundary for individual panels.
 * Catches rendering errors in a specific panel (FlowCanvas, PropertyPanel,
 * ReplayPanel, Toolbar, Modals) without tearing down the
 * entire modeler UI.
 *
 * Features:
 * - Automatic retry with exponential backoff (up to MAX_AUTO_RETRIES)
 * - Manual "Try Again" button with configurable retry limit
 * - Centralized error reporting via reportError()
 * - Recovery status tracking (attempted / succeeded)
 * - Error deduplication through the reporting layer
 */
import React, { Component } from 'react';
import { FiAlertTriangle, FiRefreshCw } from 'react-icons/fi';
import { t } from '../utils/translation';
import { reportError, markRecoveryAttempted } from '../utils/errorReporting';
import { ERROR_RECOVERY } from '../constants/dimensions';

interface PanelErrorBoundaryProps {
  children: React.ReactNode;
  /** Name displayed in the fallback UI (e.g. "Canvas", "Properties") */
  panelName: string;
  /** Optional CSS class applied to the fallback container */
  className?: string;
}

interface PanelErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
  /** Number of automatic retries attempted so far */
  autoRetryCount: number;
  /** Number of manual retries attempted so far */
  manualRetryCount: number;
  /** Whether an auto-retry is currently scheduled */
  isAutoRetrying: boolean;
  /** ID from the error reporting system for the current error */
  errorRecordId: string | null;
}

class PanelErrorBoundary extends Component<PanelErrorBoundaryProps, PanelErrorBoundaryState> {
  private autoRetryTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(props: PanelErrorBoundaryProps) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      autoRetryCount: 0,
      manualRetryCount: 0,
      isAutoRetrying: false,
      errorRecordId: null,
    };
  }

  static getDerivedStateFromError(error: Error): Partial<PanelErrorBoundaryState> {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo): void {
    console.error(`PanelErrorBoundary [${this.props.panelName}] caught an error:`, error, errorInfo);

    // Report to centralized error log
    const record = reportError(
      this.props.panelName,
      error,
      'error',
      errorInfo.componentStack ?? undefined,
    );
    this.setState({ errorRecordId: record.id });

    // Attempt automatic retry if within limits
    const { autoRetryCount } = this.state;
    if (autoRetryCount < ERROR_RECOVERY.MAX_AUTO_RETRIES) {
      this.scheduleAutoRetry(autoRetryCount);
    }
  }

  componentWillUnmount(): void {
    if (this.autoRetryTimer !== null) {
      clearTimeout(this.autoRetryTimer);
    }
  }

  /** Schedule an auto-retry with exponential backoff. */
  private scheduleAutoRetry(currentCount: number): void {
    const delay = ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * Math.pow(2, currentCount);
    this.setState({ isAutoRetrying: true });

    this.autoRetryTimer = setTimeout(() => {
      this.autoRetryTimer = null;
      this.setState((prev) => ({
        hasError: false,
        error: null,
        autoRetryCount: prev.autoRetryCount + 1,
        isAutoRetrying: false,
      }));
    }, delay);
  }

  /** Manual retry triggered by the user clicking "Try Again". */
  handleRetry = (): void => {
    // Cancel any pending auto-retry
    if (this.autoRetryTimer !== null) {
      clearTimeout(this.autoRetryTimer);
      this.autoRetryTimer = null;
    }

    // Mark the previous error's recovery as attempted
    if (this.state.errorRecordId) {
      markRecoveryAttempted(this.state.errorRecordId, false); // will update to true if render succeeds
    }

    this.setState((prev) => ({
      hasError: false,
      error: null,
      isAutoRetrying: false,
      manualRetryCount: prev.manualRetryCount + 1,
      errorRecordId: null,
    }));
  };

  render(): React.ReactNode {
    if (this.state.hasError) {
      const { autoRetryCount, manualRetryCount, isAutoRetrying } = this.state;
      const totalRetries = autoRetryCount + manualRetryCount;
      const canManualRetry = manualRetryCount < ERROR_RECOVERY.MAX_MANUAL_RETRIES;

      return (
        <div className={`panel-error-fallback ${this.props.className || ''}`}>
          <div className="panel-error-content">
            <FiAlertTriangle className="panel-error-icon" aria-hidden="true" />
            <h4>{t('@panel encountered an error', { '@panel': this.props.panelName })}</h4>
            <p>{t('This section failed to render. The rest of the modeler is still available.')}</p>

            {isAutoRetrying ? (
              <div className="panel-error-auto-retry">
                <FiRefreshCw className="panel-error-spinner" aria-hidden="true" />
                <span>{t('Retrying automatically...')}</span>
              </div>
            ) : canManualRetry ? (
              <button className="panel-error-retry" onClick={this.handleRetry}>
                {t('Try Again')}
              </button>
            ) : (
              <p className="panel-error-exhausted">
                {t('Recovery attempts exhausted. Please refresh the page.')}
              </p>
            )}

            {totalRetries > 0 && (
              <p className="panel-error-retry-count">
                {t('@count recovery attempt(s)', { '@count': String(totalRetries) })}
              </p>
            )}

            <details className="panel-error-details">
              <summary>{t('Error details')}</summary>
              <pre>{(this.state.error && this.state.error.toString()) || t('Unknown error')}</pre>
            </details>
          </div>
        </div>
      );
    }

    // If we just recovered successfully from a retry, mark it in the report
    if (this.state.errorRecordId && (this.state.autoRetryCount > 0 || this.state.manualRetryCount > 0)) {
      markRecoveryAttempted(this.state.errorRecordId, true);
    }

    return this.props.children;
  }
}

export default PanelErrorBoundary;
