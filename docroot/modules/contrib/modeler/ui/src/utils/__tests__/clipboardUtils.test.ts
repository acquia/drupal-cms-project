import { TextEncoder, TextDecoder } from 'util';

// Polyfill TextEncoder/TextDecoder for Jest
global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder as typeof global.TextDecoder;

import {
  generateNodeId,
  generateEdgeId,
  generateUniqueEdgeId,
  copyElements,
  pasteElements,
} from '../clipboardUtils';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';

// Mock localStorage
const localStorageMock = (() => {
  let store: { [key: string]: string } = {};
  return {
    getItem: jest.fn((key: string) => store[key] || null),
    setItem: jest.fn((key: string, value: string) => {
      store[key] = value;
    }),
    removeItem: jest.fn((key: string) => {
      delete store[key];
    }),
    clear: jest.fn(() => {
      store = {};
    }),
  };
})();

// Mock sessionStorage
const sessionStorageMock = (() => {
  let store: { [key: string]: string } = {};
  return {
    getItem: jest.fn((key: string) => store[key] || null),
    setItem: jest.fn((key: string, value: string) => {
      store[key] = value;
    }),
    removeItem: jest.fn((key: string) => {
      delete store[key];
    }),
    clear: jest.fn(() => {
      store = {};
    }),
  };
})();

Object.defineProperty(window, 'localStorage', { value: localStorageMock });
Object.defineProperty(window, 'sessionStorage', { value: sessionStorageMock });

// Mock Web Crypto API
const mockCryptoKey = {} as CryptoKey;
const mockEncryptedData = new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16]);

const mockSubtle = {
  generateKey: jest.fn().mockResolvedValue(mockCryptoKey),
  exportKey: jest.fn().mockResolvedValue({ kty: 'oct', k: 'test-key' }),
  importKey: jest.fn().mockResolvedValue(mockCryptoKey),
  encrypt: jest.fn().mockResolvedValue(mockEncryptedData.buffer),
  decrypt: jest.fn().mockImplementation(async (_algo, _key, _data: ArrayBuffer) => {
    // Return the original data that was "encrypted"
    const encoder = new TextEncoder();
    return encoder.encode('{"type":"workflow-elements","nodes":[],"edges":[],"timestamp":' + Date.now() + '}').buffer;
  }),
};

Object.defineProperty(window, 'crypto', {
  value: {
    subtle: mockSubtle,
    getRandomValues: jest.fn((arr: Uint8Array) => {
      for (let i = 0; i < arr.length; i++) {
        arr[i] = Math.floor(Math.random() * 256);
      }
      return arr;
    }),
  },
});

describe('clipboardUtils', () => {
  beforeEach(() => {
    localStorageMock.clear();
    sessionStorageMock.clear();
    jest.clearAllMocks();
  });

  describe('generateNodeId', () => {
    it('should generate ID with label in snake_case', () => {
      const id = generateNodeId('My Node Label', 'element');
      expect(id).toMatch(/^my_node_label_[a-z0-9]{8}$/);
    });

    it('should use type when no label is provided', () => {
      const id = generateNodeId('', 'gateway');
      expect(id).toMatch(/^gateway_[a-z0-9]{8}$/);
    });

    it('should use "node" as default type when neither is provided', () => {
      const id = generateNodeId();
      expect(id).toMatch(/^node_[a-z0-9]{8}$/);
    });

    it('should handle special characters in label', () => {
      const id = generateNodeId('My @Special# Label!', 'element');
      expect(id).toMatch(/^my_special_label_[a-z0-9]{8}$/);
    });

    it('should handle multiple spaces', () => {
      const id = generateNodeId('Multiple   Spaces   Here', 'element');
      expect(id).toMatch(/^multiple_spaces_here_[a-z0-9]{8}$/);
    });

    it('should generate unique IDs', () => {
      const id1 = generateNodeId('Test', 'element');
      const id2 = generateNodeId('Test', 'element');
      expect(id1).not.toBe(id2);
    });
  });

  describe('generateEdgeId', () => {
    it('should generate ID with source and target', () => {
      const id = generateEdgeId('source_123', 'target_456');
      expect(id).toMatch(/^source_123_to_target_456_[a-z0-9]{8}$/);
    });

    it('should generate unique IDs', () => {
      const id1 = generateEdgeId('src', 'tgt');
      const id2 = generateEdgeId('src', 'tgt');
      expect(id1).not.toBe(id2);
    });
  });

  describe('generateUniqueEdgeId', () => {
    it('should generate edge ID with prefix', () => {
      const id = generateUniqueEdgeId();
      expect(id).toMatch(/^edge_[a-z0-9]{8}$/);
    });
  });

  describe('copyElements', () => {
    const mockNodes: Node[] = [
      {
        id: 'node_1',
        type: 'element',
        position: { x: 100, y: 200 },
        data: { label: 'Test Node', plugin: 'test_plugin' },
      },
    ];

    const mockEdges: Edge[] = [
      {
        id: 'edge_1',
        source: 'node_1',
        target: 'node_2',
        type: 'default',
      },
    ];

    it('should encrypt and store data in localStorage', async () => {
      copyElements(mockNodes, mockEdges);

      // Wait for async encryption to complete
      await new Promise(resolve => setTimeout(resolve, 10));

      expect(localStorageMock.setItem).toHaveBeenCalledWith(
        'workflow-modeler-clipboard',
        expect.any(String)
      );

      // Find the clipboard data call (not the encryption key call)
      const clipboardCall = localStorageMock.setItem.mock.calls.find(
        (call: string[]) => call[0] === 'workflow-modeler-clipboard'
      );
      expect(clipboardCall).toBeDefined();
      const storedData = JSON.parse(clipboardCall![1]);
      expect(storedData.version).toBe(1);
      expect(storedData.encrypted).toBeDefined();
    });

    it('should attempt to copy to system clipboard', () => {
      copyElements(mockNodes, mockEdges);
      expect(navigator.clipboard.writeText).toHaveBeenCalled();
    });
  });

  describe('pasteElements', () => {
    beforeEach(() => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [
          {
            id: 'original_node',
            type: 'element',
            position: { x: 100, y: 100 },
            data: { label: 'Test' },
          },
        ],
        edges: [],
        timestamp: Date.now(),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));
    });

    it('should paste elements with mouse position offset', async () => {
      const mousePos = { x: 200, y: 300 };

      const result = await pasteElements([], [], mousePos);

      // With mouse position, offset should be calculated from PASTE_OFFSET constant
      expect(result.nodes).toHaveLength(1);
      expect(result.nodes[0].position.x).toBeDefined();
      expect(result.nodes[0].position.y).toBeDefined();
    });

    it('should paste elements with default offset when no mouse position', async () => {
      const result = await pasteElements([], [], null);

      expect(result.nodes).toHaveLength(1);
      // Default offset should be applied
      expect(result.nodes[0].position.x).not.toBe(100);
    });

    it('should return empty arrays when clipboard is empty', async () => {
      localStorageMock.getItem.mockReturnValue(null);
      const result = await pasteElements([], [], null);
      expect(result.nodes).toHaveLength(0);
      expect(result.edges).toHaveLength(0);
    });

    it('should generate new IDs for pasted nodes', async () => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [
          {
            id: 'original_node_1',
            type: 'element',
            position: { x: 100, y: 200 },
            data: { label: 'Test Node', plugin: 'test_plugin' },
          },
          {
            id: 'original_node_2',
            type: 'gateway',
            position: { x: 300, y: 200 },
            data: { label: 'Gateway' },
          },
        ],
        edges: [
          {
            id: 'original_edge_1',
            source: 'original_node_1',
            target: 'original_node_2',
            type: 'default',
          },
        ],
        timestamp: Date.now(),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], null);

      expect(result.nodes).toHaveLength(2);
      expect(result.nodes[0].id).not.toBe('original_node_1');
      expect(result.nodes[1].id).not.toBe('original_node_2');
    });

    it('should update edge source and target references', async () => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [
          {
            id: 'original_node_1',
            type: 'element',
            position: { x: 100, y: 200 },
            data: { label: 'Test Node', plugin: 'test_plugin' },
          },
          {
            id: 'original_node_2',
            type: 'gateway',
            position: { x: 300, y: 200 },
            data: { label: 'Gateway' },
          },
        ],
        edges: [
          {
            id: 'original_edge_1',
            source: 'original_node_1',
            target: 'original_node_2',
            type: 'default',
          },
        ],
        timestamp: Date.now(),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], null);

      expect(result.edges).toHaveLength(1);
      expect(result.edges[0].id).not.toBe('original_edge_1');
      expect(result.edges[0].source).toBe(result.nodes[0].id);
      expect(result.edges[0].target).toBe(result.nodes[1].id);
    });

    it('should preserve node data', async () => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [
          {
            id: 'original_node_1',
            type: 'element',
            position: { x: 100, y: 200 },
            data: { label: 'Test Node', plugin: 'test_plugin' },
          },
        ],
        edges: [],
        timestamp: Date.now(),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], null);

      expect(result.nodes[0].data.label).toBe('Test Node');
      expect(result.nodes[0].data.plugin).toBe('test_plugin');
      expect(result.nodes[0].type).toBe('element');
    });

    it('should filter out edges with missing source or target', async () => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [
          { id: 'node_1', position: { x: 0, y: 0 }, data: {} },
        ],
        edges: [
          { id: 'edge_1', source: 'node_1', target: 'missing_node' },
        ],
        timestamp: Date.now(),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], null);
      expect(result.edges).toHaveLength(0);
    });

    it('should handle nodes without position property', async () => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [
          { id: 'node_1', data: { label: 'No Position' } }, // No position
        ],
        edges: [],
        timestamp: Date.now(),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], { x: 150, y: 150 });

      // Position should include the offset from mouse position
      expect(result.nodes[0].position.x).toBeDefined();
      expect(result.nodes[0].position.y).toBeDefined();
    });

    it('should return empty for invalid data type in legacy format', async () => {
      localStorageMock.getItem.mockReturnValue(
        JSON.stringify({ type: 'invalid', nodes: [], edges: [], timestamp: Date.now() })
      );

      const result = await pasteElements([], [], null);
      expect(result.nodes).toHaveLength(0);
      expect(result.edges).toHaveLength(0);
    });

    it('should handle legacy unencrypted data', async () => {
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [{ id: 'node_1', position: { x: 0, y: 0 }, data: {} }],
        edges: [],
        timestamp: Date.now(),
      };

      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], null);
      expect(result.nodes).toHaveLength(1);
    });

    it('should return empty for expired legacy data (over 24 hours)', async () => {
      const oldTimestamp = Date.now() - (25 * 60 * 60 * 1000); // 25 hours ago
      const clipboardData = {
        type: 'workflow-elements',
        nodes: [],
        edges: [],
        timestamp: oldTimestamp,
      };

      localStorageMock.getItem.mockReturnValue(JSON.stringify(clipboardData));

      const result = await pasteElements([], [], null);
      expect(result.nodes).toHaveLength(0);
      expect(result.edges).toHaveLength(0);
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('workflow-modeler-clipboard');
    });

    it('should return empty for invalid JSON', async () => {
      // Suppress expected console.warn for invalid JSON
      const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

      localStorageMock.getItem.mockReturnValue('invalid json');
      const result = await pasteElements([], [], null);
      expect(result.nodes).toHaveLength(0);
      expect(result.edges).toHaveLength(0);

      expect(warnSpy).toHaveBeenCalledWith(
        'Failed to read from clipboard:',
        expect.any(SyntaxError)
      );
      warnSpy.mockRestore();
    });
  });

  describe('encrypted data handling', () => {
    it('should handle encrypted clipboard data (version 1) via pasteElements', async () => {
      // Setup mock to return decrypted data
      mockSubtle.decrypt.mockResolvedValueOnce(
        new TextEncoder().encode(JSON.stringify({
          type: 'workflow-elements',
          nodes: [{ id: 'node_1', position: { x: 0, y: 0 }, data: {} }],
          edges: [],
          timestamp: Date.now(),
        })).buffer
      );

      const encryptedStorage = {
        version: 1,
        encrypted: btoa(String.fromCharCode(...new Uint8Array(28))), // Mock encrypted data
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(encryptedStorage));

      const result = await pasteElements([], [], null);

      expect(mockSubtle.decrypt).toHaveBeenCalled();
      expect(result.nodes).toHaveLength(1);
    });

    it('should return empty for expired encrypted data via pasteElements', async () => {
      const oldTimestamp = Date.now() - (25 * 60 * 60 * 1000); // 25 hours ago
      mockSubtle.decrypt.mockResolvedValueOnce(
        new TextEncoder().encode(JSON.stringify({
          type: 'workflow-elements',
          nodes: [],
          edges: [],
          timestamp: oldTimestamp,
        })).buffer
      );

      const encryptedStorage = {
        version: 1,
        encrypted: btoa(String.fromCharCode(...new Uint8Array(28))),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(encryptedStorage));

      const result = await pasteElements([], [], null);

      expect(result.nodes).toHaveLength(0);
      expect(result.edges).toHaveLength(0);
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('workflow-modeler-clipboard');
    });

    it('should return empty for invalid type in encrypted data via pasteElements', async () => {
      mockSubtle.decrypt.mockResolvedValueOnce(
        new TextEncoder().encode(JSON.stringify({
          type: 'invalid-type',
          nodes: [],
          edges: [],
          timestamp: Date.now(),
        })).buffer
      );

      const encryptedStorage = {
        version: 1,
        encrypted: btoa(String.fromCharCode(...new Uint8Array(28))),
      };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(encryptedStorage));

      const result = await pasteElements([], [], null);
      expect(result.nodes).toHaveLength(0);
      expect(result.edges).toHaveLength(0);
    });

    it('should re-import existing key from localStorage via copyElements', async () => {
      // Set up existing key in localStorage (shared across tabs)
      const mockKeyData = { kty: 'oct', k: 'existing-key' };
      localStorageMock.getItem.mockReturnValue(JSON.stringify(mockKeyData));
      localStorageMock.clear();
      jest.clearAllMocks();

      // Need to re-mock localStorage to return the key
      localStorageMock.getItem.mockReturnValue(JSON.stringify(mockKeyData));

      // copyElements will trigger key creation/import
      const nodes: Node[] = [{ id: 'node_1', position: { x: 0, y: 0 }, data: {} }];
      copyElements(nodes, []);

      await new Promise(resolve => setTimeout(resolve, 10));

      // importKey should be called since key exists in localStorage
      expect(mockSubtle.importKey).toHaveBeenCalled();
    });
  });

  describe('encryption error handling', () => {
    it('should log warning when encryption fails via copyElements', async () => {
      const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
      mockSubtle.encrypt.mockRejectedValueOnce(new Error('Encryption failed'));

      const nodes: Node[] = [{ id: 'node_1', position: { x: 0, y: 0 }, data: {} }];
      copyElements(nodes, []);

      await new Promise(resolve => setTimeout(resolve, 50));

      expect(warnSpy).toHaveBeenCalledWith(
        'Failed to encrypt clipboard data:',
        expect.any(Error)
      );
      warnSpy.mockRestore();
    });
  });
});
