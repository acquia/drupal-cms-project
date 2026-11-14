import '@testing-library/jest-dom';

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
});

// Mock ResizeObserver — stores callback so tests can trigger resize events
// via (window as any).__triggerResizeObserver()
let _resizeObserverCallback: ResizeObserverCallback | null = null;

class ResizeObserverMock {
  constructor(callback: ResizeObserverCallback) {
    _resizeObserverCallback = callback;
  }
  observe = jest.fn();
  unobserve = jest.fn();
  disconnect = jest.fn();
}
window.ResizeObserver = ResizeObserverMock as unknown as typeof ResizeObserver;

(window as any).__triggerResizeObserver = (
  entries: Partial<ResizeObserverEntry>[] = [],
) => {
  _resizeObserverCallback?.(
    entries as ResizeObserverEntry[],
    new ResizeObserverMock(_resizeObserverCallback!) as unknown as ResizeObserver,
  );
};

// Mock IntersectionObserver
class IntersectionObserverMock {
  observe = jest.fn();
  unobserve = jest.fn();
  disconnect = jest.fn();
  root = null;
  rootMargin = '';
  thresholds = [];
}
window.IntersectionObserver = IntersectionObserverMock as any;

// Mock Drupal globals
(global as any).Drupal = {
  behaviors: {},
  t: (str: string, args?: Record<string, string | number>) => {
    if (args) {
      let result = str;
      for (const [key, value] of Object.entries(args)) {
        result = result.replace(new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), String(value));
      }
      return result;
    }
    return str;
  },
  url: (path: string) => path,
};

(global as any).drupalSettings = {
  path: {
    baseUrl: '/',
  },
};

// Mock fetch
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    json: () => Promise.resolve({}),
    text: () => Promise.resolve(''),
  })
) as jest.Mock;

// Mock clipboard API
Object.assign(navigator, {
  clipboard: {
    writeText: jest.fn(() => Promise.resolve()),
    readText: jest.fn(() => Promise.resolve('')),
  },
});

// Suppress console errors in tests unless explicitly needed
const originalError = console.error;
beforeAll(() => {
  console.error = (...args: any[]) => {
    // Filter out known non-critical warnings
    if (typeof args[0] === 'string') {
      // Deprecated ReactDOM.render warning
      if (args[0].includes('Warning: ReactDOM.render is no longer supported')) {
        return;
      }
      // act() warnings from userEvent - these are false positives
      // See: https://github.com/testing-library/react-testing-library/issues/1051
      if (args[0].includes('Warning: An update to') && args[0].includes('inside a test was not wrapped in act')) {
        return;
      }
      // Expected error logging from DocumentationPopup error handling tests
      if (args[0].includes('Error fetching documentation:')) {
        return;
      }
    }
    // jsdom doesn't implement navigation — link.click() in downloadFile
    // triggers this error during export tests.  Not a real problem.
    if (String(args[0]).includes('Not implemented: navigation')) {
      return;
    }
    originalError.call(console, ...args);
  };
});

afterAll(() => {
  console.error = originalError;
});
