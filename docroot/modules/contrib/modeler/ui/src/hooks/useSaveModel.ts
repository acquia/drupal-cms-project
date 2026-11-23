/**
 * useSaveModel - Hook for handling model save operations
 * 
 * Manages the Drupal AJAX save process including CSRF token fetching,
 * model data serialization, and save completion callbacks.
 */

import { useCallback } from 'react';
import { t } from '../utils/translation';
import { validateCsrfToken } from '../utils/validation';
import { showDrupalMessage } from '../utils/drupalMessage';
import type { Settings, DrupalAjax } from '../types/settings';

/** jQuery Deferred-like object returned by `$.get()`. */
interface JQueryGetResult {
  done: (callback: (data: string) => void) => JQueryGetResult;
  fail: (callback: (xhr: { status: number }, status: string, error: string) => void) => JQueryGetResult;
}

declare global {
  interface Window {
    workflowModelerData?: Record<string, unknown>;
    jQuery?: {
      get: (url: string) => JQueryGetResult;
    };
  }
}

interface UseSaveModelProps {
  /** Callback to get model data for saving */
  onSave?: () => Record<string, unknown> | null;
  /** Callback after successful save */
  onSaveComplete?: () => void;
  /** Application settings including API URLs */
  settings?: Settings;
  /** Drupal AJAX object */
  drupal?: DrupalAjax;
  /** Screen reader announcement callback */
  announce?: (text: string) => void;
  /**
   * Pre-save validation callback.  Return an error message string to block
   * the save and display the error, or `null` to allow saving.
   */
  validateBeforeSave?: () => string | null;
}

interface UseSaveModelReturn {
  /** Handler function for save button click */
  handleSave: (e: React.MouseEvent) => void;
}

/**
 * Get model data from available sources
 */
function getModelData(onSave?: () => Record<string, unknown> | null): Record<string, unknown> | null {
  // Try callback first
  if (onSave) {
    const data = onSave();
    if (data) return data;
  }

  // Fallback to global window data
  if (window.workflowModelerData) {
    return window.workflowModelerData;
  }

  return null;
}

/**
 * Create absolute URL from potentially relative path
 */
function createAbsoluteUrl(url: string): string {
  return url.startsWith('/') ? window.location.origin + url : url;
}

export function useSaveModel({
  onSave,
  onSaveComplete,
  settings = {},
  drupal,
  announce,
  validateBeforeSave,
}: UseSaveModelProps): UseSaveModelReturn {
  const handleSave = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    // Run pre-save validation (e.g. placeholder node check)
    if (validateBeforeSave) {
      const validationError = validateBeforeSave();
      if (validationError) {
        if (announce) {
          announce(validationError);
        }
        showDrupalMessage(validationError, 'error');
        return;
      }
    }

    // Validate Drupal availability
    if (!drupal?.ajax) {
      console.error('Drupal object not available');
      return;
    }

    // Get model data
    const rawModelData = getModelData(onSave);
    if (!rawModelData) {
      console.error('No model data available to save');
      return;
    }

    // Convert to JSON string for submission
    const modelData = JSON.stringify(rawModelData);

    // Validate API settings
    if (!settings.modeler_api) {
      console.error('modeler_api settings not found');
      return;
    }

    const tokenUrl = settings.modeler_api.token_url;
    const saveUrl = settings.modeler_api.save_url;

    if (!tokenUrl || !saveUrl) {
      console.error('Missing modeler API URLs');
      return;
    }

    // Validate jQuery availability
    if (!window.jQuery) {
      console.error('jQuery not available');
      return;
    }

    // Clear any existing messages
    const messagesList = document.querySelector('.messages-list');
    if (messagesList) {
      messagesList.innerHTML = '';
    }

    // Fetch CSRF token and then save
    const absoluteTokenUrl = createAbsoluteUrl(tokenUrl);

    window.jQuery.get(absoluteTokenUrl)
      .done((token: string) => {
        // Validate the CSRF token before using it
        let validatedToken: string;
        try {
          // jQuery.get returns the response body; we create a minimal
          // Response-like object for the shared validator (status is 200
          // because jQuery .done only fires on success).
          validatedToken = validateCsrfToken(
            { ok: true, status: 200, statusText: 'OK' } as Response,
            typeof token === 'string' ? token : String(token),
          );
        } catch (err) {
          console.error('Invalid CSRF token:', err instanceof Error ? err.message : err);
          if (announce) {
            announce(t('Failed to save model.'));
          }
          return;
        }

        // Create Drupal AJAX request settings
        const ajaxSettings = {
          url: saveUrl,
          submit: modelData,
          beforeSend: function(xhr: XMLHttpRequest) {
            xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
            xhr.setRequestHeader('X-CSRF-Token', validatedToken);
            xhr.setRequestHeader('X-Modeler-API-isNew', settings.modeler_api?.isNew ? 'true' : 'false');
          },
          progress: {
            type: 'fullscreen',
            message: t('Saving model...')
          }
        };

        // Create and execute Drupal AJAX request
        const ajaxObject = drupal.ajax(ajaxSettings);
        const originalSuccess = ajaxObject.success?.bind(ajaxObject);
        
        ajaxObject.success = function(response: unknown, status: unknown) {
          const result = originalSuccess?.(response, status);
          return Promise.resolve(result).then(() => {
            // Check if the Drupal response contains error commands.
            // Drupal AJAX responses are arrays of command objects; error
            // messages use the "message" command with messageOptions.type
            // set to "error", or the "insert" command with error markup.
            const hasError = Array.isArray(response) && response.some(
              (cmd: Record<string, unknown>) =>
                (cmd.command === 'message' && ((cmd.messageOptions as Record<string, unknown> | undefined)?.type === 'error' || cmd.type === 'error')) ||
                (cmd.command === 'insert' && typeof cmd.data === 'string' && cmd.data.includes('messages--error'))
            );

            if (hasError) {
              if (announce) {
                announce(t('Failed to save model.'));
              }
            } else {
              // Reset unsaved changes indicator only on successful save
              if (onSaveComplete) {
                onSaveComplete();
              }
              if (announce) {
                announce(t('Model saved successfully.'));
              }
            }
            return result;
          }).catch((err: unknown) => {
            console.error('Save failed:', err instanceof Error ? err.message : err);
            if (announce) {
              announce(t('Failed to save model.'));
            }
          });
        };

        // Handle HTTP-level errors (network failures, 500s, etc.)
        const originalError = ajaxObject.error?.bind(ajaxObject);
        ajaxObject.error = function(xmlhttprequest: unknown, uri: unknown, customMessage: unknown) {
          if (originalError) {
            originalError(xmlhttprequest, uri, customMessage);
          }
          const xhr = xmlhttprequest as { status?: number } | null;
          console.error('AJAX save failed:', xhr?.status, customMessage);
          if (announce) {
            announce(t('Failed to save model.'));
          }
        };
        
        ajaxObject.execute();
      })
      .fail((xhr: { status: number }, _status: string, _error: string) => {
        console.error('Failed to get CSRF token:', xhr.status);
        if (announce) {
          announce(t('Failed to save model.'));
        }
      });
  }, [onSave, onSaveComplete, settings, drupal, announce, validateBeforeSave]);

  return { handleSave };
}
