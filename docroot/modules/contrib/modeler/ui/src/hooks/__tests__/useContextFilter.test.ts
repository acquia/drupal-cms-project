import { renderHook } from '@testing-library/react';
import { useContextFilter } from '../useContextFilter';
import { useContextStore } from '../../store/useContextStore';
import { useGraphStore } from '../../store/useGraphStore';
import type { ModelerContext, ModelerDependencies } from '../../types/settings';

// Mock the stores
jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn(),
}));
jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn(),
}));

const mockedUseContextStore = useContextStore as unknown as jest.Mock;
const mockedUseGraphStore = useGraphStore as unknown as jest.Mock;

const mockComponents = [
  { plugin: 'event:insert', label: 'Entity Insert', type: 'start', componentType: 1 },
  { plugin: 'event:update', label: 'Entity Update', type: 'start', componentType: 1 },
  { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:delete', label: 'Delete Entity', type: 'element', componentType: 4 },
  { plugin: 'condition:is_new', label: 'Entity is New', type: 'link', componentType: 5 },
  { plugin: 'gateway:exclusive', label: 'Exclusive Gateway', type: 'gateway', componentType: 6 },
];

const mockContexts: ModelerContext[] = [
  {
    id: 'ctx_content',
    topic: 'Content Editing',
    model_owner: 'test_owner',
    components: {
      start: { plugins: ['event:insert'] },
      element: { plugins: ['action:save'] },
      link: { plugins: ['condition:is_new'] },
    },
  },
  {
    id: 'ctx_user',
    topic: 'User Management',
    model_owner: 'test_owner',
    components: {
      start: { plugins: ['event:update'] },
      element: { plugins: ['action:delete'] },
      gateway: { plugins: ['gateway:exclusive'] },
    },
  },
];

interface SetupOptions {
  nodes?: any[];
  edges?: any[];
  dependencies?: ModelerDependencies;
}

function setupMock(
  selectedContextId: string | null,
  contexts: ModelerContext[],
  options: SetupOptions = {}
) {
  const { nodes = [], edges = [], dependencies = {} } = options;
  mockedUseContextStore.mockImplementation((selector: (state: any) => any) => {
    const state = {
      selectedContextId,
      contexts,
      dependencies,
    };
    return selector(state);
  });
  mockedUseGraphStore.mockImplementation((selector: (state: any) => any) => {
    const state = {
      nodes,
      edges,
    };
    return selector(state);
  });
}

describe('useContextFilter', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should return all components when no context is selected', () => {
    setupMock(null, mockContexts);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toEqual(mockComponents);
  });

  it('should return all components when contexts array is empty', () => {
    setupMock('ctx_content', []);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toEqual(mockComponents);
  });

  it('should return all components when selectedContextId does not match any context', () => {
    setupMock('ctx_nonexistent', mockContexts);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toEqual(mockComponents);
  });

  it('should filter components to only those in the selected context', () => {
    setupMock('ctx_content', mockContexts);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toHaveLength(3);
    expect(result.current.map((c: any) => c.plugin)).toEqual([
      'event:insert',
      'action:save',
      'condition:is_new',
    ]);
  });

  it('should filter correctly for a different context', () => {
    setupMock('ctx_user', mockContexts);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toHaveLength(3);
    expect(result.current.map((c: any) => c.plugin)).toEqual([
      'event:update',
      'action:delete',
      'gateway:exclusive',
    ]);
  });

  it('should return empty array when context has no matching plugins', () => {
    const contextWithUnknownPlugins: ModelerContext[] = [{
      id: 'ctx_empty',
      topic: 'Empty',
      model_owner: 'test_owner',
      components: {
        start: { plugins: ['unknown:plugin'] },
      },
    }];

    setupMock('ctx_empty', contextWithUnknownPlugins);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toHaveLength(0);
  });

  it('should handle context with empty components object', () => {
    const contextWithEmptyComponents: ModelerContext[] = [{
      id: 'ctx_empty',
      topic: 'Empty',
      model_owner: 'test_owner',
      components: {},
    }];

    setupMock('ctx_empty', contextWithEmptyComponents);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toHaveLength(0);
  });

  it('should handle empty input components array', () => {
    setupMock('ctx_content', mockContexts);

    const { result } = renderHook(() => useContextFilter([]));
    expect(result.current).toEqual([]);
  });

  it('should collect plugins from all component types in the context', () => {
    const contextWithMultipleTypes: ModelerContext[] = [{
      id: 'ctx_multi',
      topic: 'Multi',
      model_owner: 'test_owner',
      components: {
        start: { plugins: ['event:insert', 'event:update'] },
        element: { plugins: ['action:save', 'action:delete'] },
        link: { plugins: ['condition:is_new'] },
        gateway: { plugins: ['gateway:exclusive'] },
      },
    }];

    setupMock('ctx_multi', contextWithMultipleTypes);

    const { result } = renderHook(() => useContextFilter(mockComponents as any));
    expect(result.current).toHaveLength(6);
  });

  describe('dependency filtering', () => {
    it('should include a plugin with satisfied node dependency', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // No context selected — dependency filtering only
      // Workflow contains a node with plugin 'event:insert' → dependency satisfied
      setupMock(null, [], {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'event:insert' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).toContain('action:save');
    });

    it('should exclude a plugin when its node dependency is not satisfied', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // Workflow has no nodes → dependency not satisfied
      setupMock(null, [], { dependencies });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).not.toContain('action:save');
    });

    it('should include a plugin when at least one of multiple dependencies is satisfied', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [
            { type: 'start', id: 'event:insert' },
            { type: 'start', id: 'event:update' },
          ],
        },
      };

      // Only event:update exists in the workflow
      setupMock(null, [], {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'event:update' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).toContain('action:save');
    });

    it('should exclude a plugin when none of multiple dependencies is satisfied', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [
            { type: 'start', id: 'event:insert' },
            { type: 'start', id: 'event:update' },
          ],
        },
      };

      // Workflow has a node with a completely different plugin
      setupMock(null, [], {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'action:delete' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).not.toContain('action:save');
    });

    it('should check edges for link-type dependencies', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'link', id: 'condition:is_new' }],
        },
      };

      // Workflow has an edge with the condition plugin
      setupMock(null, [], {
        dependencies,
        edges: [{ id: 'e1', source: 'n1', target: 'n2', data: { condition: 'condition:is_new' } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).toContain('action:save');
    });

    it('should not satisfy a link-type dependency from node plugins', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'link', id: 'condition:is_new' }],
        },
      };

      // A node has 'condition:is_new' as its plugin, but it's not on an edge
      setupMock(null, [], {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'condition:is_new' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).not.toContain('action:save');
    });

    it('should not filter plugins without dependencies even when other plugins have them', () => {
      const dependencies: ModelerDependencies = {
        element: {
          // Only action:save has dependencies; action:delete has none
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // No nodes → action:save excluded, but action:delete still included
      setupMock(null, [], { dependencies });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).not.toContain('action:save');
      expect(result.current.map((c: any) => c.plugin)).toContain('action:delete');
    });

    it('should handle dependencies across multiple component type entries', () => {
      const dependencies: ModelerDependencies = {
        start: {
          'event:insert': [{ type: 'element', id: 'action:save' }],
        },
      };

      // action:save exists as a node → event:insert dependency satisfied
      setupMock(null, [], {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'action:save' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).toContain('event:insert');
      expect(result.current.map((c: any) => c.plugin)).toContain('action:save');
    });

    it('should handle mixed link and node dependencies (any satisfied is enough)', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [
            { type: 'start', id: 'event:insert' },
            { type: 'link', id: 'condition:is_new' },
          ],
        },
      };

      // Only the edge condition exists, not the start node
      setupMock(null, [], {
        dependencies,
        edges: [{ id: 'e1', source: 'n1', target: 'n2', data: { condition: 'condition:is_new' } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).toContain('action:save');
    });

    it('should handle empty dependencies (no dependency filtering applied)', () => {
      // No dependencies defined → all components pass through
      setupMock(null, []);

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current).toHaveLength(6);
    });
  });

  describe('combined context and dependency filtering', () => {
    it('should apply both context and dependency filters', () => {
      const contexts: ModelerContext[] = [{
        id: 'ctx_content',
        topic: 'Content',
        model_owner: 'test_owner',
        components: {
          start: { plugins: ['event:insert'] },
          element: { plugins: ['action:save'] },
        },
      }];
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // Context allows event:insert and action:save.
      // action:save requires event:insert in the workflow, but workflow is empty.
      setupMock('ctx_content', contexts, { dependencies });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      // Only event:insert passes (action:save fails dependency check)
      expect(result.current.map((c: any) => c.plugin)).toEqual(['event:insert']);
    });

    it('should pass both filters when context and dependencies are satisfied', () => {
      const contexts: ModelerContext[] = [{
        id: 'ctx_content',
        topic: 'Content',
        model_owner: 'test_owner',
        components: {
          start: { plugins: ['event:insert'] },
          element: { plugins: ['action:save'] },
        },
      }];
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // action:save passes context check AND dependency is satisfied
      setupMock('ctx_content', contexts, {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'event:insert' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).toEqual(['event:insert', 'action:save']);
    });

    it('should exclude plugins not in context even if dependencies are satisfied', () => {
      const contexts: ModelerContext[] = [{
        id: 'ctx_content',
        topic: 'Content',
        model_owner: 'test_owner',
        components: {
          start: { plugins: ['event:insert'] },
          // action:delete is NOT in context
        },
      }];
      const dependencies: ModelerDependencies = {
        element: {
          'action:delete': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // action:delete has satisfied dependencies but is not in the context
      setupMock('ctx_content', contexts, {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'event:insert' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      expect(result.current.map((c: any) => c.plugin)).not.toContain('action:delete');
    });

    it('should apply dependencies without context when no context is selected', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // No context selected, but dependencies are defined
      setupMock(null, mockContexts, {
        dependencies,
        nodes: [{ id: 'n1', data: { plugin: 'event:insert' }, position: { x: 0, y: 0 } }],
      });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      // All plugins pass context check (none selected), action:save passes dependency check
      expect(result.current).toHaveLength(6);
    });

    it('should filter by dependencies even without context selected', () => {
      const dependencies: ModelerDependencies = {
        element: {
          'action:save': [{ type: 'start', id: 'event:insert' }],
        },
      };

      // No context selected, dependency unsatisfied
      setupMock(null, mockContexts, { dependencies });

      const { result } = renderHook(() => useContextFilter(mockComponents as any));
      // action:save excluded due to unsatisfied dependency, rest pass
      expect(result.current).toHaveLength(5);
      expect(result.current.map((c: any) => c.plugin)).not.toContain('action:save');
    });
  });
});
