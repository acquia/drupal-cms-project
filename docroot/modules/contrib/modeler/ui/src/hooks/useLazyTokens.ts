import { useState, useEffect } from 'react';
import type { GlobalToken } from '../types/settings';

/**
 * Fetches token data on demand from a modeler_api endpoint.
 *
 * Used for both global tokens and template tokens.  The modeler_api module
 * provides a URL in drupalSettings instead of inlining the (potentially
 * expensive) token data during the initial page render.  This hook fetches
 * from that URL once and caches the result in component state.
 *
 * For backward compatibility, if the tokens are already present in
 * drupalSettings (inline), those are used directly without a network request.
 *
 * @param url - The endpoint URL (e.g. `global_tokens_url` or `template_tokens_url`).
 * @param initialTokens - Tokens from drupalSettings if still provided inline.
 * @returns The tokens record, or undefined while loading.
 */
export function useLazyTokens(
  url?: string,
  initialTokens?: Record<string, GlobalToken>,
): Record<string, GlobalToken> | undefined {
  const [tokens, setTokens] = useState(initialTokens);

  useEffect(() => {
    // If tokens were provided inline via drupalSettings, use them directly.
    if (initialTokens && Object.keys(initialTokens).length > 0) {
      setTokens(initialTokens);
      return;
    }

    // No URL means the backend does not support lazy loading.
    if (!url) {
      return;
    }

    // AbortController ensures that React Strict Mode's double-mount cycle
    // cancels the first request at the browser level, preventing duplicate
    // network calls.
    const controller = new AbortController();

    fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Token request failed: ${response.status}`);
        }
        return response.json() as Promise<Record<string, GlobalToken>>;
      })
      .then((data) => {
        setTokens(data);
      })
      .catch((error: unknown) => {
        // AbortError is expected when the effect cleans up (e.g. Strict Mode
        // remount).  All other errors are silently ignored; the token panel
        // will simply stay empty, which is an acceptable degradation.
        if (error instanceof DOMException && error.name === 'AbortError') {
          return;
        }
      });

    return () => {
      controller.abort();
    };
  }, [url, initialTokens]);

  return tokens;
}
