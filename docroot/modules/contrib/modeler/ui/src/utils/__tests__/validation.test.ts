/**
 * Tests for response validation utilities
 */

import {
  validateCsrfToken,
  fetchValidatedCsrfToken,
  validateReplayEntries,
  validateConfigurationResponse,
  validateModelDataShape,
  validateDocumentationResponse,
} from '../validation';

// Helper to create a minimal Response-like object
function mockResponse(overrides: Partial<Response> = {}): Response {
  return {
    ok: true,
    status: 200,
    statusText: 'OK',
    headers: new Headers(),
    ...overrides,
  } as Response;
}

// ---------------------------------------------------------------------------
// validateCsrfToken
// ---------------------------------------------------------------------------

describe('validateCsrfToken', () => {
  it('should return trimmed token for valid response', () => {
    expect(validateCsrfToken(mockResponse(), '  abc123token  ')).toBe('abc123token');
  });

  it('should throw when response is not ok', () => {
    const response = mockResponse({ ok: false, status: 403, statusText: 'Forbidden' });
    expect(() => validateCsrfToken(response, 'some-token')).toThrow(/403/);
  });

  it('should throw for empty token', () => {
    expect(() => validateCsrfToken(mockResponse(), '')).toThrow(/empty/);
  });

  it('should throw for whitespace-only token', () => {
    expect(() => validateCsrfToken(mockResponse(), '   ')).toThrow(/empty/);
  });

  it('should throw when token looks like HTML (starts with <)', () => {
    expect(() => validateCsrfToken(mockResponse(), '<html><body>Error</body></html>')).toThrow(/HTML/);
  });

  it('should throw when token looks like HTML (starts with <!)', () => {
    expect(() => validateCsrfToken(mockResponse(), '<!DOCTYPE html>')).toThrow(/HTML/);
  });

  it('should throw when token is excessively long', () => {
    const longToken = 'a'.repeat(513);
    expect(() => validateCsrfToken(mockResponse(), longToken)).toThrow(/large/);
  });

  it('should accept a valid 43-character Drupal token', () => {
    const drupalToken = 'YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXoxMjM0NQ';
    expect(validateCsrfToken(mockResponse(), drupalToken)).toBe(drupalToken);
  });

  it('should accept token at 512 character boundary', () => {
    const token = 'x'.repeat(512);
    expect(validateCsrfToken(mockResponse(), token)).toBe(token);
  });
});

// ---------------------------------------------------------------------------
// fetchValidatedCsrfToken
// ---------------------------------------------------------------------------

describe('fetchValidatedCsrfToken', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
  });

  it('should fetch and validate a CSRF token', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      statusText: 'OK',
      text: () => Promise.resolve('valid-token'),
    });

    const token = await fetchValidatedCsrfToken('/api/token');
    expect(token).toBe('valid-token');
    expect(global.fetch).toHaveBeenCalledWith('/api/token', undefined);
  });

  it('should pass signal to fetch when provided', async () => {
    const controller = new AbortController();
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      statusText: 'OK',
      text: () => Promise.resolve('valid-token'),
    });

    await fetchValidatedCsrfToken('/api/token', controller.signal);
    expect(global.fetch).toHaveBeenCalledWith('/api/token', { signal: controller.signal });
  });

  it('should throw for failed response', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: false,
      status: 500,
      statusText: 'Internal Server Error',
      text: () => Promise.resolve('error'),
    });

    await expect(fetchValidatedCsrfToken('/api/token')).rejects.toThrow(/500/);
  });

  it('should throw for empty token response', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      statusText: 'OK',
      text: () => Promise.resolve(''),
    });

    await expect(fetchValidatedCsrfToken('/api/token')).rejects.toThrow(/empty/);
  });
});

// ---------------------------------------------------------------------------
// validateReplayEntries
// ---------------------------------------------------------------------------

describe('validateReplayEntries', () => {
  const validEntry = {
    model_id: 'model-1',
    component_id: 'event-1',
    history: [],
    timestamp: '2026-02-09 10:00:00',
    user: 'admin',
    ip: '127.0.0.1',
    url: '/node/1',
  };

  it('should return empty array for empty array input', () => {
    expect(validateReplayEntries([])).toEqual([]);
  });

  it('should return valid entries from array', () => {
    const result = validateReplayEntries([validEntry]);
    expect(result).toHaveLength(1);
    expect(result[0].model_id).toBe('model-1');
  });

  it('should throw for non-array input', () => {
    expect(() => validateReplayEntries('not-array')).toThrow(/Unexpected response format/);
  });

  it('should throw for object input', () => {
    expect(() => validateReplayEntries({ data: [] })).toThrow(/Unexpected response format/);
  });

  it('should throw for null input', () => {
    expect(() => validateReplayEntries(null)).toThrow(/Unexpected response format/);
  });

  it('should filter out invalid entries and warn', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const entries = [validEntry, { invalid: true }, validEntry];

    const result = validateReplayEntries(entries);
    expect(result).toHaveLength(2);
    expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('1 invalid'));
    consoleSpy.mockRestore();
  });

  it('should report correct indices of invalid entries', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const entries = [{ bad: 1 }, validEntry, { bad: 2 }, { bad: 3 }];

    const result = validateReplayEntries(entries);
    expect(result).toHaveLength(1);
    expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('indices: 0, 2, 3'));
    consoleSpy.mockRestore();
  });

  it('should return all valid entries when all are valid', () => {
    const entries = [validEntry, { ...validEntry, component_id: 'event-2' }];
    const result = validateReplayEntries(entries);
    expect(result).toHaveLength(2);
  });

  it('should return empty array when all entries are invalid', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const entries = [{ bad: 1 }, { bad: 2 }];

    const result = validateReplayEntries(entries);
    expect(result).toHaveLength(0);
    expect(consoleSpy).toHaveBeenCalled();
    consoleSpy.mockRestore();
  });
});

// ---------------------------------------------------------------------------
// validateConfigurationResponse
// ---------------------------------------------------------------------------

describe('validateConfigurationResponse', () => {
  const validFormResponse = {
    form: [
      { key: 'field1', type: 'textfield', title: 'Field 1' },
    ],
  };

  it('should extract form array from valid response', () => {
    const { form } = validateConfigurationResponse(validFormResponse);
    expect(form).toEqual([{ key: 'field1', type: 'textfield', title: 'Field 1' }]);
  });

  it('should return null form and null error for null input', () => {
    const result = validateConfigurationResponse(null);
    expect(result.form).toBeNull();
    expect(result.error).toBeNull();
  });

  it('should return null form and null error for undefined input', () => {
    const result = validateConfigurationResponse(undefined);
    expect(result.form).toBeNull();
    expect(result.error).toBeNull();
  });

  it('should return null form and null error for empty object', () => {
    const result = validateConfigurationResponse({});
    expect(result.form).toBeNull();
    expect(result.error).toBeNull();
  });

  it('should throw for non-object input (string)', () => {
    expect(() => validateConfigurationResponse('not-object')).toThrow(/not an object/);
  });

  it('should throw for non-object input (array)', () => {
    expect(() => validateConfigurationResponse([1, 2])).toThrow(/not an object/);
  });

  it('should return error string when present', () => {
    const result = validateConfigurationResponse({ error: 'Invalid plugin.' });
    expect(result.form).toBeNull();
    expect(result.error).toBe('Invalid plugin.');
  });

  it('should return both form and error when both are present', () => {
    const result = validateConfigurationResponse({
      form: [{ key: 'field1', type: 'textfield' }],
      error: 'Some warning',
    });
    expect(result.form).toEqual([{ key: 'field1', type: 'textfield' }]);
    expect(result.error).toBe('Some warning');
  });

  it('should ignore non-string error values', () => {
    const result = validateConfigurationResponse({
      error: 42,
      form: [{ key: 'field1', type: 'textfield' }],
    });
    expect(result.error).toBeNull();
    expect(result.form).toEqual([{ key: 'field1', type: 'textfield' }]);
  });

  it('should warn and return null form when form is not an array', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const result = validateConfigurationResponse({ form: 'not-an-array' });
    expect(result.form).toBeNull();
    expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('not an array'));
    consoleSpy.mockRestore();
  });

  it('should warn and return null form when form is a plain object', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const result = validateConfigurationResponse({ form: { key: 'value' } });
    expect(result.form).toBeNull();
    expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('not an array'));
    consoleSpy.mockRestore();
  });
});

// ---------------------------------------------------------------------------
// validateModelDataShape
// ---------------------------------------------------------------------------

describe('validateModelDataShape', () => {
  it('should return empty warnings for valid model data', () => {
    const data = {
      nodes: [{ id: 'node-1', type: 'element' }],
      edges: [{ source: 'node-1', target: 'node-2' }],
    };
    // Note: 'node-2' doesn't exist but that's an orphaned edge warning
    const warnings = validateModelDataShape(data);
    // Should have warning about orphaned edge
    expect(warnings.some(w => w.includes('non-existent target'))).toBe(true);
  });

  it('should return no warnings for fully valid data', () => {
    const data = {
      nodes: [
        { id: 'node-1', type: 'start' },
        { id: 'node-2', type: 'element' },
      ],
      edges: [{ source: 'node-1', target: 'node-2' }],
    };
    expect(validateModelDataShape(data)).toEqual([]);
  });

  it('should warn for null data', () => {
    const warnings = validateModelDataShape(null);
    expect(warnings).toHaveLength(1);
    expect(warnings[0]).toContain('empty');
  });

  it('should warn for undefined data', () => {
    const warnings = validateModelDataShape(undefined);
    expect(warnings).toHaveLength(1);
  });

  it('should warn for non-object data', () => {
    const warnings = validateModelDataShape('string');
    expect(warnings[0]).toContain('not an object');
  });

  it('should warn when nodes is not an array', () => {
    const warnings = validateModelDataShape({ nodes: 'not-array' });
    expect(warnings.some(w => w.includes('not an array'))).toBe(true);
  });

  it('should warn when edges is not an array', () => {
    const warnings = validateModelDataShape({ edges: 123 });
    expect(warnings.some(w => w.includes('not an array'))).toBe(true);
  });

  it('should warn for node without id', () => {
    const warnings = validateModelDataShape({ nodes: [{ type: 'element' }] });
    expect(warnings.some(w => w.includes('index 0') && w.includes('id'))).toBe(true);
  });

  it('should warn for node with non-string id', () => {
    const warnings = validateModelDataShape({ nodes: [{ id: 42 }] });
    expect(warnings.some(w => w.includes('non-string id'))).toBe(true);
  });

  it('should warn for edge without source', () => {
    const warnings = validateModelDataShape({ edges: [{ target: 'node-1' }] });
    expect(warnings.some(w => w.includes('source'))).toBe(true);
  });

  it('should warn for edge without target', () => {
    const warnings = validateModelDataShape({ edges: [{ source: 'node-1' }] });
    expect(warnings.some(w => w.includes('target'))).toBe(true);
  });

  it('should warn for edge that is not an object', () => {
    const warnings = validateModelDataShape({ edges: ['not-object'] });
    expect(warnings.some(w => w.includes('not an object'))).toBe(true);
  });

  it('should warn for orphaned edge source', () => {
    const data = {
      nodes: [{ id: 'node-1' }],
      edges: [{ source: 'node-999', target: 'node-1' }],
    };
    const warnings = validateModelDataShape(data);
    expect(warnings.some(w => w.includes('non-existent source'))).toBe(true);
  });

  it('should warn for orphaned edge target', () => {
    const data = {
      nodes: [{ id: 'node-1' }],
      edges: [{ source: 'node-1', target: 'node-999' }],
    };
    const warnings = validateModelDataShape(data);
    expect(warnings.some(w => w.includes('non-existent target'))).toBe(true);
  });

  it('should accept data without nodes or edges', () => {
    const warnings = validateModelDataShape({ id: 'model-1', metadata: {} });
    expect(warnings).toEqual([]);
  });

  it('should handle empty nodes and edges arrays', () => {
    const warnings = validateModelDataShape({ nodes: [], edges: [] });
    expect(warnings).toEqual([]);
  });

  it('should report multiple warnings', () => {
    const data = {
      nodes: [{ type: 'bad' }, { id: 42 }],
      edges: 'bad',
    };
    const warnings = validateModelDataShape(data);
    expect(warnings.length).toBeGreaterThan(1);
  });
});

// ---------------------------------------------------------------------------
// validateDocumentationResponse
// ---------------------------------------------------------------------------

describe('validateDocumentationResponse', () => {
  it('should not throw for valid HTML response', () => {
    const headers = new Headers({ 'content-type': 'text/html; charset=utf-8' });
    const response = mockResponse({ headers });
    expect(() => validateDocumentationResponse(response)).not.toThrow();
  });

  it('should not throw for text/plain response', () => {
    const headers = new Headers({ 'content-type': 'text/plain' });
    const response = mockResponse({ headers });
    expect(() => validateDocumentationResponse(response)).not.toThrow();
  });

  it('should not throw for xhtml response', () => {
    const headers = new Headers({ 'content-type': 'application/xhtml+xml' });
    const response = mockResponse({ headers });
    expect(() => validateDocumentationResponse(response)).not.toThrow();
  });

  it('should throw for non-ok response', () => {
    const response = mockResponse({ ok: false, status: 404, statusText: 'Not Found' });
    expect(() => validateDocumentationResponse(response)).toThrow(/404/);
  });

  it('should warn for unexpected Content-Type (JSON)', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const headers = new Headers({ 'content-type': 'application/json' });
    const response = mockResponse({ headers });

    validateDocumentationResponse(response);

    expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('application/json'));
    consoleSpy.mockRestore();
  });

  it('should not warn when no Content-Type header is present', () => {
    const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
    const response = mockResponse();

    validateDocumentationResponse(response);

    expect(consoleSpy).not.toHaveBeenCalled();
    consoleSpy.mockRestore();
  });

  it('should include status in error message', () => {
    const response = mockResponse({
      ok: false,
      status: 503,
      statusText: 'Service Unavailable',
    });
    expect(() => validateDocumentationResponse(response)).toThrow(/503.*Service Unavailable/);
  });
});
