// Clipboard utilities for copy/paste functionality across browser tabs

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { LAYOUT } from '../constants/dimensions';
import { safeJsonParse } from './validation';

const STORAGE_KEY = 'workflow-modeler-clipboard';
const KEY_STORAGE_KEY = 'workflow-modeler-crypto-key';

// ============ Web Crypto API Encryption ============

// Get or create encryption key shared across all tabs
const getOrCreateKey = async (): Promise<CryptoKey> => {
  try {
    // Check if we have a key in localStorage (shared across tabs)
    const storedKey = localStorage.getItem(KEY_STORAGE_KEY);
    if (storedKey) {
      const keyData = JSON.parse(storedKey);
      return await crypto.subtle.importKey(
        'jwk',
        keyData,
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
      );
    }

    // Generate a new key
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );

    // Export and store in localStorage so all tabs can decrypt
    const exportedKey = await crypto.subtle.exportKey('jwk', key);
    localStorage.setItem(KEY_STORAGE_KEY, JSON.stringify(exportedKey));

    return key;
  } catch {
    throw new Error('Failed to initialize encryption key');
  }
};

// Encrypt data using AES-GCM
const encryptData = async (data: string): Promise<string> => {
  const key = await getOrCreateKey();
  const encoder = new TextEncoder();
  const iv = crypto.getRandomValues(new Uint8Array(12));

  const encrypted = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv },
    key,
    encoder.encode(data)
  );

  // Combine IV and encrypted data, then base64 encode
  const combined = new Uint8Array(iv.length + encrypted.byteLength);
  combined.set(iv);
  combined.set(new Uint8Array(encrypted), iv.length);

  return btoa(String.fromCharCode(...combined));
};

// Decrypt data using AES-GCM
const decryptData = async (encryptedData: string): Promise<string> => {
  const key = await getOrCreateKey();

  // Decode base64 and extract IV and encrypted data
  const combined = new Uint8Array(
    atob(encryptedData).split('').map(c => c.charCodeAt(0))
  );
  const iv = combined.slice(0, 12);
  const data = combined.slice(12);

  const decrypted = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv },
    key,
    data
  );

  return new TextDecoder().decode(decrypted);
};

// Convert a label to snake_case
const toSnakeCase = (str: string): string => {
  if (!str) return '';
  return str
    .trim()
    .toLowerCase()
    .replace(/[^\w\s]/g, '') // Remove special characters
    .replace(/\s+/g, '_')     // Replace spaces with underscores
    .replace(/_+/g, '_')      // Replace multiple underscores with single
    .replace(/^_|_$/g, '');   // Remove leading/trailing underscores
};

// Generate 8-digit hash
const generate8DigitHash = (): string => {
  return Math.random().toString(36).substr(2, 8).padEnd(8, '0');
};

// Generate unique IDs for nodes based on label or type
export const generateNodeId = (label: string = '', type: string = 'node'): string => {
  const base = label ? toSnakeCase(label) : type.toLowerCase();
  const hash = generate8DigitHash();
  return `${base}_${hash}`;
};

// Generate unique IDs for edges
export const generateEdgeId = (sourceId: string, targetId: string): string => {
  const hash = generate8DigitHash();
  return `${sourceId}_to_${targetId}_${hash}`;
};

// Generate unique edge ID (alias for compatibility)
export const generateUniqueEdgeId = (): string => {
  const hash = generate8DigitHash();
  return `edge_${hash}`;
};

interface ClipboardData {
  type: 'workflow-elements';
  nodes: Node[];
  edges: Edge[];
  timestamp: number;
}

interface EncryptedStorage {
  encrypted: string;
  version: 1;
}

// Copy selected elements to clipboard
export const copyElements = (selectedNodes: Node[], selectedEdges: Edge[]): void => {
  copyToClipboard(selectedNodes, selectedEdges);
};

// Copy selected elements to clipboard (async for encryption)
const copyToClipboard = (nodes: Node[], edges: Edge[]): void => {
  const clipboardData: ClipboardData = {
    type: 'workflow-elements',
    nodes,
    edges,
    timestamp: Date.now()
  };

  // Encrypt and store asynchronously
  encryptData(JSON.stringify(clipboardData))
    .then(encrypted => {
      const storage: EncryptedStorage = { encrypted, version: 1 };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    })
    .catch(error => {
      console.warn('Failed to encrypt clipboard data:', error);
    });

  // Also copy to system clipboard as JSON for cross-application use
  if (navigator.clipboard) {
    navigator.clipboard.writeText(JSON.stringify(clipboardData, null, 2)).catch(() => {
      // Silently fail if clipboard access is denied
    });
  }
};

// Get clipboard data (async for decryption)
const getFromClipboard = async (): Promise<ClipboardData | null> => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (!stored) return null;

    const storage = safeJsonParse<Partial<EncryptedStorage & ClipboardData>>(stored);

    // Handle encrypted data (version 1+)
    if (storage.version === 1 && storage.encrypted) {
      const decrypted = await decryptData(storage.encrypted);
      const data = safeJsonParse<ClipboardData>(decrypted);

      if (data.type !== 'workflow-elements') return null;

      // Check if data is not too old (24 hours)
      const age = Date.now() - data.timestamp;
      if (age > 24 * 60 * 60 * 1000) {
        localStorage.removeItem(STORAGE_KEY);
        return null;
      }

      return data;
    }

    // Handle legacy unencrypted data (migrate on next copy)
    if (storage.type === 'workflow-elements') {
      const legacy = storage as ClipboardData;
      const age = Date.now() - legacy.timestamp;
      if (age > 24 * 60 * 60 * 1000) {
        localStorage.removeItem(STORAGE_KEY);
        return null;
      }
      return legacy;
    }

    return null;
  } catch (error) {
    console.warn('Failed to read from clipboard:', error);
    return null;
  }
};

// Paste elements with new IDs to avoid conflicts (compatibility function)
export const pasteElements = async (
  currentNodes: Node[],
  currentEdges: Edge[],
  mousePosition: { x: number; y: number } | null = null
): Promise<{ nodes: Node[], edges: Edge[] }> => {
  const offsetX = mousePosition ? mousePosition.x - LAYOUT.PASTE_OFFSET : LAYOUT.DEFAULT_PASTE_OFFSET;
  const offsetY = mousePosition ? mousePosition.y - LAYOUT.PASTE_OFFSET : LAYOUT.DEFAULT_PASTE_OFFSET;
  return pasteFromClipboard(offsetX, offsetY);
};

// Paste elements with new IDs to avoid conflicts (async for decryption)
const pasteFromClipboard = async (
  offsetX: number = 0,
  offsetY: number = 0
): Promise<{ nodes: Node[], edges: Edge[] }> => {
  const clipboardData = await getFromClipboard();
  if (!clipboardData) return { nodes: [], edges: [] };

  const idMapping: { [oldId: string]: string } = {};

  // Create new nodes with updated IDs and positions
  const newNodes: Node[] = clipboardData.nodes.map(node => {
    const newId = generateNodeId(node.data?.label || '', node.type || 'element');
    idMapping[node.id] = newId;

    return {
      ...node,
      id: newId,
      position: {
        x: (node.position?.x || 0) + offsetX,
        y: (node.position?.y || 0) + offsetY
      }
    };
  });

  // Create new edges with updated IDs and source/target references
  const newEdges: Edge[] = clipboardData.edges.map(edge => {
    const newSourceId = idMapping[edge.source];
    const newTargetId = idMapping[edge.target];

    // Skip edges where source or target wasn't copied
    if (!newSourceId || !newTargetId) return null;

    const newId = generateEdgeId(newSourceId, newTargetId);

    return {
      ...edge,
      id: newId,
      source: newSourceId,
      target: newTargetId
    };
  }).filter(Boolean) as Edge[];

  return { nodes: newNodes, edges: newEdges };
};

