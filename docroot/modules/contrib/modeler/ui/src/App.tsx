import { Component } from 'react';
import { ReactFlowProvider } from 'reactflow';
import Flow from './components/Flow';
import { useUISettingsStore } from './store/useUISettingsStore';
import { t } from './utils/translation';
import { reportError, markRecoveryAttempted } from './utils/errorReporting';
import { ERROR_RECOVERY } from './constants/dimensions';
import type { Settings, DrupalAjax } from './types/settings';

// Error Boundary to catch React DevTools and other component errors
interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
  retryCount: number;
  errorRecordId: string | null;
}

interface ErrorBoundaryProps {
  children: React.ReactNode;
}

class ModelerErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false, error: null, retryCount: 0, errorRecordId: null };
  }

  static getDerivedStateFromError(error: Error): Partial<ErrorBoundaryState> {
    // Safely check for React DevTools permission errors
    const message = error?.message || error?.toString() || '';
    if (message && message.includes('Permission denied') && message.includes('nodeType')) {
      console.debug('Suppressed React DevTools error in Error Boundary:', message);
      return { hasError: false }; // Don't show error UI for DevTools issues
    }
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    // Safely check for React DevTools permission errors
    const message = error?.message || error?.toString() || '';
    if (message && message.includes('Permission denied') && message.includes('nodeType')) {
      console.debug('Caught React DevTools error in Error Boundary:', message);
      return; // Don't log or show error for DevTools issues
    }
    
    console.error('Modeler Error Boundary caught an error:', error, errorInfo);

    // Report to centralized error log
    const record = reportError(
      t('Modeler'),
      error,
      'error',
      errorInfo.componentStack ?? undefined,
    );
    this.setState({ errorRecordId: record.id });
  }

  handleRetry = (): void => {
    // Mark previous recovery as attempted
    if (this.state.errorRecordId) {
      markRecoveryAttempted(this.state.errorRecordId, false);
    }
    this.setState((prev) => ({
      hasError: false,
      error: null,
      retryCount: prev.retryCount + 1,
      errorRecordId: null,
    }));
  };

  render() {
    if (this.state.hasError) {
      const canRetry = this.state.retryCount < ERROR_RECOVERY.MAX_MANUAL_RETRIES;

      return (
        <div className="modeler-error-boundary">
          <h3>{t('Something went wrong')}</h3>
          <p>{t('The workflow modeler encountered an error. Please try again or refresh the page.')}</p>
          {canRetry ? (
            <button className="panel-error-retry modeler-error-retry" onClick={this.handleRetry}>
              {t('Try Again')}
            </button>
          ) : (
            <p className="panel-error-exhausted">
              {t('Recovery attempts exhausted. Please refresh the page.')}
            </p>
          )}
          {this.state.retryCount > 0 && (
            <p className="panel-error-retry-count">
              {t('@count recovery attempt(s)', { '@count': String(this.state.retryCount) })}
            </p>
          )}
          <details>
            <summary>{t('Error details')}</summary>
            <pre>
              {this.state.error ? this.state.error.toString() : t('Unknown error')}
            </pre>
          </details>
        </div>
      );
    }

    // If recovered from a retry, mark recovery as successful
    if (this.state.errorRecordId && this.state.retryCount > 0) {
      markRecoveryAttempted(this.state.errorRecordId, true);
    }

    return this.props.children;
  }
}

interface AppProps {
  settings: Settings;
  drupal: DrupalAjax;
}

export default function App({ settings, drupal }: AppProps) {
  const darkMode = useUISettingsStore(state => state.darkMode);

  return (
    <ModelerErrorBoundary>
      <div className={`modeler ${darkMode ? 'dark-mode' : ''}${settings?.modeler?.standalone ? ' standalone' : ''}`}>
        <ReactFlowProvider>
          <Flow settings={settings} drupal={drupal} />
        </ReactFlowProvider>
      </div>
    </ModelerErrorBoundary>
  );
}
