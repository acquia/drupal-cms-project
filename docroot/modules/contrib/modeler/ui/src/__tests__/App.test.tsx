import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import App from '../App';
import { _resetForTesting } from '../utils/errorReporting';
import { ERROR_RECOVERY } from '../constants/dimensions';

jest.mock('reactflow', () => ({
  ReactFlowProvider: ({ children }: any) => <div data-testid="reactflow-provider">{children}</div>,
}));

let shouldThrow = false;
let throwError: Error = new Error('Test error');

jest.mock('../components/Flow', () => {
  return function MockFlow({ settings, drupal: _drupal }: any) {
    if (shouldThrow) throw throwError;
    return <div data-testid="flow-component" data-model-id={settings?.modeler?.modelId} />;
  };
});

describe('App', () => {
  const defaultProps = {
    settings: {
      modeler: { modelId: 'test-model' },
      modeler_api: { save_url: '/save' },
    },
    drupal: {
      ajax: jest.fn(),
    },
  };

  beforeEach(() => {
    jest.clearAllMocks();
    shouldThrow = false;
    throwError = new Error('Test error');
    _resetForTesting();
  });

  describe('rendering', () => {
    it('should render without crashing', () => {
      const { container } = render(<App {...defaultProps} />);
      expect(container.querySelector('.modeler')).toBeTruthy();
    });

    it('should wrap Flow in ReactFlowProvider', () => {
      render(<App {...defaultProps} />);
      expect(screen.getByTestId('reactflow-provider')).toBeTruthy();
      expect(screen.getByTestId('flow-component')).toBeTruthy();
    });

    it('should pass settings to Flow', () => {
      render(<App {...defaultProps} />);
      expect(screen.getByTestId('flow-component').getAttribute('data-model-id')).toBe('test-model');
    });
  });

  describe('error boundary', () => {
    it('should show error UI when child throws a real error', () => {
      shouldThrow = true;
      throwError = new Error('Something broke');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      expect(screen.getByText('Something went wrong')).toBeTruthy();
      expect(screen.getByText(/encountered an error/)).toBeTruthy();
      expect(screen.getByText('Error details')).toBeTruthy();
      expect(screen.getByText(/Something broke/)).toBeTruthy();

      errorSpy.mockRestore();
    });

    it('should display error.toString() in details', () => {
      shouldThrow = true;
      throwError = new Error('Detailed failure');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);
      expect(screen.getByText(/Detailed failure/)).toBeTruthy();

      errorSpy.mockRestore();
    });

    it('should log real errors via componentDidCatch', () => {
      shouldThrow = true;
      throwError = new Error('Component crash');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      // componentDidCatch should have called console.error with the error
      expect(errorSpy).toHaveBeenCalledWith(
        'Modeler Error Boundary caught an error:',
        expect.any(Error),
        expect.anything()
      );

      errorSpy.mockRestore();
    });

    it('should handle error with no message property', () => {
      shouldThrow = true;
      throwError = { toString: () => 'Raw error object' } as any;
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      expect(screen.getByText('Something went wrong')).toBeTruthy();

      errorSpy.mockRestore();
    });

    it('should show a Try Again button on the first error', () => {
      shouldThrow = true;
      throwError = new Error('Crash');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      expect(screen.getByText('Try Again')).toBeTruthy();

      errorSpy.mockRestore();
    });

    it('should recover when Try Again is clicked and error is fixed', () => {
      shouldThrow = true;
      throwError = new Error('Crash');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      expect(screen.getByText('Something went wrong')).toBeTruthy();

      // Fix the error
      shouldThrow = false;
      fireEvent.click(screen.getByText('Try Again'));

      expect(screen.getByTestId('flow-component')).toBeTruthy();
      expect(screen.queryByText('Something went wrong')).not.toBeTruthy();

      errorSpy.mockRestore();
    });

    it('should show error again if retry fails', () => {
      shouldThrow = true;
      throwError = new Error('Persistent error');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      fireEvent.click(screen.getByText('Try Again'));

      expect(screen.getByText('Something went wrong')).toBeTruthy();
      expect(screen.getByText('1 recovery attempt(s)')).toBeTruthy();

      errorSpy.mockRestore();
    });

    it('should show exhausted message after max manual retries', () => {
      shouldThrow = true;
      throwError = new Error('Persistent error');
      const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      render(<App {...defaultProps} />);

      // Exhaust all manual retries
      for (let i = 0; i < ERROR_RECOVERY.MAX_MANUAL_RETRIES; i++) {
        fireEvent.click(screen.getByText('Try Again'));
      }

      expect(screen.queryByText('Try Again')).not.toBeTruthy();
      expect(screen.getByText(/Recovery attempts exhausted/)).toBeTruthy();

      errorSpy.mockRestore();
    });
  });

});
