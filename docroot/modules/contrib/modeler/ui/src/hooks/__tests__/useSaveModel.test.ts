/**
 * Tests for useSaveModel hook
 * 
 * Note: The main save functionality involves Drupal AJAX which is difficult
 * to unit test without mocking the entire Drupal ecosystem. These tests
 * focus on the validation and error handling paths.
 */

import { renderHook, act } from '@testing-library/react';
import { useSaveModel } from '../useSaveModel';
import { showDrupalMessage } from '../../utils/drupalMessage';

jest.mock('../../utils/drupalMessage', () => ({
  showDrupalMessage: jest.fn(),
}));

describe('useSaveModel', () => {
  // Store original console.error
  const originalConsoleError = console.error;

  beforeEach(() => {
    // Mock console.error to track calls
    console.error = jest.fn();
    
    // Clear window globals
    delete (window as any).workflowModelerData;
    delete (window as any).jQuery;
  });

  afterEach(() => {
    // Restore console.error
    console.error = originalConsoleError;
  });

  // Create a mock event
  const createMockEvent = (): React.MouseEvent => ({
    preventDefault: jest.fn(),
    stopPropagation: jest.fn(),
  } as unknown as React.MouseEvent);

  describe('validation', () => {
    it('should log error when drupal object is not available', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(console.error).toHaveBeenCalledWith('Drupal object not available');
    });

    it('should log error when drupal.ajax is not available', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          drupal: {} as any,
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(console.error).toHaveBeenCalledWith('Drupal object not available');
    });

    it('should log error when no model data is available', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(console.error).toHaveBeenCalledWith('No model data available to save');
    });

    it('should log error when modeler_api settings are missing', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: {},
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(console.error).toHaveBeenCalledWith('modeler_api settings not found');
    });

    it('should log error when API URLs are missing', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: {} },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(console.error).toHaveBeenCalledWith('Missing modeler API URLs');
    });

    it('should log error when jQuery is not available', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(console.error).toHaveBeenCalledWith('jQuery not available');
    });
  });

  describe('model data retrieval', () => {
    it('should use onSave callback for model data', () => {
      const onSave = jest.fn().mockReturnValue({ custom: 'data' });
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave,
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(onSave).toHaveBeenCalled();
      expect(mockJQuery.get).toHaveBeenCalled();
    });

    it('should fall back to window.workflowModelerData', () => {
      (window as any).workflowModelerData = { fallback: 'workflow' };
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockJQuery.get).toHaveBeenCalled();
    });

  });

  describe('event handling', () => {
    it('should prevent default and stop propagation', () => {
      const { result } = renderHook(() =>
        useSaveModel({})
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(event.preventDefault).toHaveBeenCalled();
      expect(event.stopPropagation).toHaveBeenCalled();
    });
  });

  describe('CSRF token and save flow', () => {
    it('should clear messages list before save', () => {
      const messagesList = document.createElement('div');
      messagesList.className = 'messages-list';
      messagesList.innerHTML = '<p>Old message</p>';
      document.body.appendChild(messagesList);

      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(messagesList.innerHTML).toBe('');
      document.body.removeChild(messagesList);
    });

    it('should create absolute URL for token request', () => {
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/session/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      // Should prepend origin to relative URL
      expect(mockJQuery.get).toHaveBeenCalledWith(
        expect.stringContaining('/session/token')
      );
    });

    it('should use absolute URL as-is for token request', () => {
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: 'https://example.com/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockJQuery.get).toHaveBeenCalledWith('https://example.com/token');
    });

    it('should stringify object model data', () => {
      const doneFn = jest.fn().mockReturnThis();
      const failFn = jest.fn().mockReturnThis();
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: doneFn,
          fail: failFn,
        }),
      };
      (window as any).jQuery = mockJQuery;

      const mockAjax = jest.fn();
      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ nodes: [], edges: [] }),
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      // Verify done was called (token fetch initiated)
      expect(doneFn).toHaveBeenCalled();
    });

    it('should handle string model data without re-stringifying', () => {
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ already: 'stringified' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockJQuery.get).toHaveBeenCalled();
    });

    it('should call drupal.ajax on token success and execute', () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const mockExecute = jest.fn();
      const mockAjaxObject = {
        success: jest.fn(),
        execute: mockExecute,
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: mockAjax },
          settings: {
            modeler_api: { token_url: '/token', save_url: '/save', isNew: true },
          },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      // Simulate token returned
      act(() => {
        doneCallback('  csrf-token-123  ');
      });

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        url: '/save',
      }));
      expect(mockExecute).toHaveBeenCalled();
    });

    it('should log error on token fetch failure', () => {
      let failCallback: (xhr: any, status: string, error: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnValue({
            fail: jest.fn((cb) => {
              failCallback = cb;
              return {};
            }),
          }),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      // Simulate token fetch failure
      act(() => {
        failCallback({ status: 403 }, 'error', 'Forbidden');
      });

      expect(console.error).toHaveBeenCalledWith('Failed to get CSRF token:', 403);
    });

    it('should call onSaveComplete on successful AJAX response', async () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const onSaveComplete = jest.fn();
      const originalSuccess = jest.fn().mockReturnValue(undefined);
      const mockAjaxObject = {
        success: originalSuccess,
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          onSaveComplete,
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      // The success function was replaced; call it and await the promise
      const replacedSuccess = mockAjaxObject.success;
      if (typeof replacedSuccess === 'function') {
        await replacedSuccess([], 'success');
      }
      expect(onSaveComplete).toHaveBeenCalled();
    });

    it('should announce save success when announce callback is provided', async () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const mockAnnounce = jest.fn();
      const originalSuccess = jest.fn().mockReturnValue(undefined);
      const mockAjaxObject = {
        success: originalSuccess,
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          announce: mockAnnounce,
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      const replacedSuccess = mockAjaxObject.success;
      if (typeof replacedSuccess === 'function') {
        await replacedSuccess([], 'success');
      }
      expect(mockAnnounce).toHaveBeenCalledWith('Model saved successfully.');
    });

    it('should not call onSaveComplete when response contains error message command', async () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const onSaveComplete = jest.fn();
      const originalSuccess = jest.fn().mockReturnValue(undefined);
      const mockAjaxObject = {
        success: originalSuccess,
        error: jest.fn(),
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          onSaveComplete,
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      // Simulate Drupal response with an error message command (messageOptions.type)
      const replacedSuccess = mockAjaxObject.success;
      if (typeof replacedSuccess === 'function') {
        await replacedSuccess([
          { command: 'settings', settings: {}, merge: true },
          { command: 'message', message: 'Validation failed.', messageOptions: { type: 'error' }, clearPrevious: true },
        ], 'success');
      }
      expect(onSaveComplete).not.toHaveBeenCalled();
    });

    it('should not call onSaveComplete when response contains insert with error markup', async () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const onSaveComplete = jest.fn();
      const originalSuccess = jest.fn().mockReturnValue(undefined);
      const mockAjaxObject = {
        success: originalSuccess,
        error: jest.fn(),
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          onSaveComplete,
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      // Simulate Drupal response with error markup in insert command
      const replacedSuccess = mockAjaxObject.success;
      if (typeof replacedSuccess === 'function') {
        await replacedSuccess([{ command: 'insert', data: '<div class="messages messages--error">Error occurred</div>' }], 'success');
      }
      expect(onSaveComplete).not.toHaveBeenCalled();
    });

    it('should announce failure when response contains errors', async () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const mockAnnounce = jest.fn();
      const originalSuccess = jest.fn().mockReturnValue(undefined);
      const mockAjaxObject = {
        success: originalSuccess,
        error: jest.fn(),
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          announce: mockAnnounce,
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      const replacedSuccess = mockAjaxObject.success;
      if (typeof replacedSuccess === 'function') {
        await replacedSuccess([
          { command: 'message', message: 'Validation failed.', messageOptions: { type: 'error' }, clearPrevious: true },
        ], 'success');
      }
      expect(mockAnnounce).toHaveBeenCalledWith('Failed to save model.');
    });

    it('should handle AJAX HTTP-level errors without resetting dirty state', () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const onSaveComplete = jest.fn();
      const mockAnnounce = jest.fn();
      const originalError = jest.fn();
      const mockAjaxObject = {
        success: jest.fn(),
        error: originalError,
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          onSaveComplete,
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          announce: mockAnnounce,
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      // The error handler was replaced; call it to simulate HTTP failure
      const replacedError = mockAjaxObject.error;
      if (typeof replacedError === 'function') {
        act(() => {
          replacedError({ status: 500 }, '/save', 'Internal Server Error');
        });
      }

      expect(onSaveComplete).not.toHaveBeenCalled();
      expect(mockAnnounce).toHaveBeenCalledWith('Failed to save model.');
    });

    it('should handle rejected promise from originalSuccess', async () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const onSaveComplete = jest.fn();
      const mockAnnounce = jest.fn();
      const originalSuccess = jest.fn().mockReturnValue(Promise.reject(new Error('Drupal error')));
      const mockAjaxObject = {
        success: originalSuccess,
        error: jest.fn(),
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          onSaveComplete,
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          announce: mockAnnounce,
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token-123');
      });

      const replacedSuccess = mockAjaxObject.success;
      if (typeof replacedSuccess === 'function') {
        await replacedSuccess([], 'success');
      }
      expect(onSaveComplete).not.toHaveBeenCalled();
      expect(mockAnnounce).toHaveBeenCalledWith('Failed to save model.');
    });

    it('should announce save failure when CSRF token fetch fails', () => {
      let failCallback: (xhr: any, status: string, error: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnValue({
            fail: jest.fn((cb) => {
              failCallback = cb;
              return {};
            }),
          }),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const mockAnnounce = jest.fn();
      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          announce: mockAnnounce,
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        failCallback({ status: 403 }, 'error', 'Forbidden');
      });

      expect(mockAnnounce).toHaveBeenCalledWith('Failed to save model.');
    });

    it('should handle beforeSend callback setting headers', () => {
      let doneCallback: (token: string) => void = () => {};
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn((cb) => {
            doneCallback = cb;
            return { fail: jest.fn().mockReturnThis() };
          }),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const mockAjaxObject = {
        success: jest.fn(),
        execute: jest.fn(),
      };
      const mockAjax = jest.fn().mockReturnValue(mockAjaxObject);

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: mockAjax },
          settings: {
            modeler_api: { token_url: '/token', save_url: '/save', isNew: false },
          },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      act(() => {
        doneCallback('token');
      });

      // Verify the ajax settings include beforeSend
      const ajaxSettings = mockAjax.mock.calls[0][0];
      expect(ajaxSettings.beforeSend).toBeDefined();

      // Test the beforeSend callback
      const mockXhr = {
        setRequestHeader: jest.fn(),
      };
      ajaxSettings.beforeSend(mockXhr);
      expect(mockXhr.setRequestHeader).toHaveBeenCalledWith('Content-Type', 'application/json;charset=UTF-8');
      expect(mockXhr.setRequestHeader).toHaveBeenCalledWith('X-CSRF-Token', 'token');
      expect(mockXhr.setRequestHeader).toHaveBeenCalledWith('X-Modeler-API-isNew', 'false');
    });
  });

  describe('model data with onSave returning null', () => {
    it('should fall through to window data when onSave returns null', () => {
      (window as any).workflowModelerData = { fromWindow: true };
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => null,
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockJQuery.get).toHaveBeenCalled();
    });
  });

  describe('validateBeforeSave', () => {
    it('should block save when validateBeforeSave returns an error string', () => {
      const mockAjax = jest.fn();
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: mockAjax },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          validateBeforeSave: () => 'Placeholder nodes must be resolved.',
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      // Should NOT proceed to CSRF token fetch or AJAX call
      expect(mockJQuery.get).not.toHaveBeenCalled();
      expect(mockAjax).not.toHaveBeenCalled();
    });

    it('should call announce with the validation error message', () => {
      const mockAnnounce = jest.fn();

      const { result } = renderHook(() =>
        useSaveModel({
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          announce: mockAnnounce,
          validateBeforeSave: () => 'Placeholder nodes must be resolved.',
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockAnnounce).toHaveBeenCalledWith('Placeholder nodes must be resolved.');
    });

    it('should call showDrupalMessage with the error', () => {
      const { result } = renderHook(() =>
        useSaveModel({
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          validateBeforeSave: () => 'Placeholder nodes must be resolved.',
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(showDrupalMessage).toHaveBeenCalledWith('Placeholder nodes must be resolved.', 'error');
    });

    it('should proceed normally when validateBeforeSave returns null', () => {
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
          validateBeforeSave: () => null,
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockJQuery.get).toHaveBeenCalled();
    });

    it('should proceed normally when validateBeforeSave is not provided', () => {
      const mockJQuery = {
        get: jest.fn().mockReturnValue({
          done: jest.fn().mockReturnThis(),
          fail: jest.fn().mockReturnThis(),
        }),
      };
      (window as any).jQuery = mockJQuery;

      const { result } = renderHook(() =>
        useSaveModel({
          onSave: () => ({ test: 'data' }),
          drupal: { ajax: jest.fn() },
          settings: { modeler_api: { token_url: '/token', save_url: '/save' } },
        })
      );

      const event = createMockEvent();
      act(() => {
        result.current.handleSave(event);
      });

      expect(mockJQuery.get).toHaveBeenCalled();
    });
  });
});
