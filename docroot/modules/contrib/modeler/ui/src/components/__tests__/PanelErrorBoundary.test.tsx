import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import PanelErrorBoundary from '../PanelErrorBoundary';
import { _resetForTesting } from '../../utils/errorReporting';
import { ERROR_RECOVERY } from '../../constants/dimensions';

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiAlertTriangle: () => <span data-testid="fi-alert-triangle" />,
  FiRefreshCw: () => <span data-testid="fi-refresh-cw" />,
}));

// A component that throws on demand
let shouldThrow = false;
let throwError = new Error('Test panel error');

function ThrowingChild() {
  if (shouldThrow) throw throwError;
  return <div data-testid="child-content">Working content</div>;
}

describe('PanelErrorBoundary', () => {
  beforeEach(() => {
    shouldThrow = false;
    throwError = new Error('Test panel error');
    _resetForTesting();
    jest.clearAllMocks();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('normal rendering', () => {
    it('should render children when no error occurs', () => {
      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.getByTestId('child-content')).toBeInTheDocument();
      expect(screen.getByText('Working content')).toBeInTheDocument();
    });

    it('should not show error UI when children render successfully', () => {
      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.queryByText(/encountered an error/)).not.toBeInTheDocument();
      expect(screen.queryByText('Try Again')).not.toBeInTheDocument();
    });
  });

  describe('error handling', () => {
    it('should show fallback UI when child throws', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.getByText(/Properties encountered an error/)).toBeInTheDocument();
      expect(screen.getByText(/This section failed to render/)).toBeInTheDocument();
      expect(screen.queryByTestId('child-content')).not.toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should display the panel name in the error message', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Replay">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.getByText(/Replay encountered an error/)).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should show the alert triangle icon', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.getByTestId('fi-alert-triangle')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should show error details in a collapsible section', () => {
      shouldThrow = true;
      throwError = new Error('Config load failed');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.getByText('Error details')).toBeInTheDocument();
      expect(screen.getByText(/Config load failed/)).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should show "Unknown error" when error has no message', () => {
      shouldThrow = true;
      throwError = { toString: () => '' } as any;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(screen.getByText('Unknown error')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should log the error via componentDidCatch', () => {
      shouldThrow = true;
      throwError = new Error('Render crash');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(errorSpy).toHaveBeenCalledWith(
        'PanelErrorBoundary [Canvas] caught an error:',
        expect.any(Error),
        expect.anything()
      );

      errorSpy.mockRestore();
    });
  });

  describe('retry functionality', () => {
    it('should show a Try Again button', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Wait for auto-retry to pass (both auto-retries fail)
      act(() => { jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY); });
      act(() => { jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * 2); });

      expect(screen.getByText('Try Again')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should re-render children when Try Again is clicked and error is resolved', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Wait out auto-retries
      act(() => { jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY); });
      act(() => { jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * 2); });

      expect(screen.queryByTestId('child-content')).not.toBeInTheDocument();

      // Fix the error and click retry
      shouldThrow = false;
      fireEvent.click(screen.getByText('Try Again'));

      expect(screen.getByTestId('child-content')).toBeInTheDocument();
      expect(screen.queryByText(/encountered an error/)).not.toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should show error again if retry fails', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Wait out auto-retries
      act(() => { jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY); });
      act(() => { jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * 2); });

      // Click retry without fixing the error
      fireEvent.click(screen.getByText('Try Again'));

      expect(screen.getByText(/Properties encountered an error/)).toBeInTheDocument();

      errorSpy.mockRestore();
    });
  });

  describe('auto-retry', () => {
    it('should show auto-retry status during the retry delay', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Should show auto-retry status
      expect(screen.getByText('Retrying automatically...')).toBeInTheDocument();
      expect(screen.getByTestId('fi-refresh-cw')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should auto-retry after the configured delay', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Fix the error before auto-retry triggers
      shouldThrow = false;

      // Advance past the first auto-retry delay
      act(() => {
        jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY);
      });

      // Should have recovered
      expect(screen.getByTestId('child-content')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should stop auto-retrying after MAX_AUTO_RETRIES', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Exhaust all auto-retries (error persists)
      for (let i = 0; i < ERROR_RECOVERY.MAX_AUTO_RETRIES; i++) {
        act(() => {
          jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * Math.pow(2, i));
        });
      }

      // Should no longer show "Retrying automatically..."
      expect(screen.queryByText('Retrying automatically...')).not.toBeInTheDocument();
      // Should show the manual "Try Again" button
      expect(screen.getByText('Try Again')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should use exponential backoff for auto-retries', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // First auto-retry triggers at BASE_DELAY
      act(() => {
        jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY - 1);
      });
      // Still waiting for first retry
      expect(screen.getByText('Retrying automatically...')).toBeInTheDocument();

      act(() => {
        jest.advanceTimersByTime(1);
      });
      // First retry happened (but error persists, so error UI shows again)
      expect(screen.getByText(/Canvas encountered an error/)).toBeInTheDocument();

      // Second auto-retry should trigger at 2x BASE_DELAY
      act(() => {
        jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * 2);
      });
      // After second retry, auto-retries are exhausted
      expect(screen.queryByText('Retrying automatically...')).not.toBeInTheDocument();

      errorSpy.mockRestore();
    });
  });

  describe('retry count display', () => {
    it('should display the total recovery attempt count', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Exhaust auto-retries
      for (let i = 0; i < ERROR_RECOVERY.MAX_AUTO_RETRIES; i++) {
        act(() => {
          jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * Math.pow(2, i));
        });
      }

      // After auto-retries, the count should include them
      expect(screen.getByText(`${ERROR_RECOVERY.MAX_AUTO_RETRIES} recovery attempt(s)`)).toBeInTheDocument();

      // Click manual retry
      fireEvent.click(screen.getByText('Try Again'));

      // Count should increase
      expect(screen.getByText(`${ERROR_RECOVERY.MAX_AUTO_RETRIES + 1} recovery attempt(s)`)).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should not show count when no retries have been attempted', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // On first error, auto-retry is pending but count is 0
      expect(screen.queryByText(/recovery attempt/)).not.toBeInTheDocument();

      errorSpy.mockRestore();
    });
  });

  describe('exhausted retries', () => {
    it('should show exhausted message when manual retries are maxed out', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <PanelErrorBoundary panelName="Canvas">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      // Exhaust auto-retries
      for (let i = 0; i < ERROR_RECOVERY.MAX_AUTO_RETRIES; i++) {
        act(() => {
          jest.advanceTimersByTime(ERROR_RECOVERY.AUTO_RETRY_BASE_DELAY * Math.pow(2, i));
        });
      }

      // Exhaust manual retries
      for (let i = 0; i < ERROR_RECOVERY.MAX_MANUAL_RETRIES; i++) {
        fireEvent.click(screen.getByText('Try Again'));
      }

      // Should show exhausted message instead of Try Again
      expect(screen.queryByText('Try Again')).not.toBeInTheDocument();
      expect(screen.getByText(/Recovery attempts exhausted/)).toBeInTheDocument();

      errorSpy.mockRestore();
    });
  });

  describe('CSS classes', () => {
    it('should apply the panel-error-fallback class', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      const { container } = render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      expect(container.querySelector('.panel-error-fallback')).toBeInTheDocument();

      errorSpy.mockRestore();
    });

    it('should apply the custom className when provided', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      const { container } = render(
        <PanelErrorBoundary panelName="Canvas" className="canvas-error">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      const fallback = container.querySelector('.panel-error-fallback');
      expect(fallback).toBeInTheDocument();
      expect(fallback).toHaveClass('canvas-error');

      errorSpy.mockRestore();
    });

    it('should not add extra class when className is not provided', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      const { container } = render(
        <PanelErrorBoundary panelName="Properties">
          <ThrowingChild />
        </PanelErrorBoundary>
      );

      const fallback = container.querySelector('.panel-error-fallback');
      expect(fallback).toBeInTheDocument();
      // Class should just be "panel-error-fallback " (with trailing space from template literal)
      expect(fallback?.className.trim()).toBe('panel-error-fallback');

      errorSpy.mockRestore();
    });
  });

  describe('isolation', () => {
    it('should not affect sibling components when one panel errors', () => {
      shouldThrow = true;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <div>
          <div data-testid="sibling">Sibling stays alive</div>
          <PanelErrorBoundary panelName="Properties">
            <ThrowingChild />
          </PanelErrorBoundary>
        </div>
      );

      // Sibling should still be rendered
      expect(screen.getByTestId('sibling')).toBeInTheDocument();
      expect(screen.getByText('Sibling stays alive')).toBeInTheDocument();
      // Error boundary shows fallback
      expect(screen.getByText(/Properties encountered an error/)).toBeInTheDocument();

      errorSpy.mockRestore();
    });
  });
});
