import {
  parseModelData,
  exportModelData,
  autoLayout,
  getFitViewport,
  isConditionReuseEnabled,
} from '../modelUtils';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';

// Deterministic uuid so that "new condition" round-trips (where the backend
// has not yet assigned a conditionId) produce a predictable, asserted id.
// modelUtils imports `v4 as uuidv4` from 'uuid'.
jest.mock('uuid', () => ({
  v4: jest.fn(() => 'mocked-uuid-v4'),
}));

describe('modelUtils', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('parseModelData', () => {
    it('should return empty arrays and null for null input', () => {
      const result = parseModelData(null);
      expect(result.nodes).toEqual([]);
      expect(result.edges).toEqual([]);
      expect(result.modelData).toBeNull();
    });

    it('should parse JSON string input', () => {
      const jsonString = JSON.stringify({
        nodes: [{ id: 'node1', type: 'element', position: { x: 100, y: 200 } }],
        edges: [],
      });

      const result = parseModelData(jsonString);
      expect(result.nodes).toHaveLength(1);
      expect(result.nodes[0].id).toBe('node1');
    });

    it('should parse object input', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'start', position: { x: 100, y: 200 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes).toHaveLength(1);
      expect(result.nodes[0].type).toBe('start');
    });

    it('should ensure node IDs are strings', () => {
      const data = {
        nodes: [{ id: 123, type: 'element', position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(typeof result.nodes[0].id).toBe('string');
      expect(result.nodes[0].id).toBe('123');
    });

    it('should set default position if not provided', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element' }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].position).toBeDefined();
      expect(result.nodes[0].position.x).toBeDefined();
      expect(result.nodes[0].position.y).toBeDefined();
    });

    it('should use node ID as label if no label provided', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].data.label).toBe('node1');
    });

    it('should preserve node configuration', () => {
      const config = { key1: 'value1', key2: 'value2' };
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 }, configuration: config }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].data.configuration).toEqual(config);
    });

    it('should resolve type from componentType when type is missing', () => {
      const data = {
        nodes: [
          { id: 'event1', componentType: 1, position: { x: 0, y: 0 }, plugin: 'content_entity:insert', label: 'Insert' },
          { id: 'action1', componentType: 4, position: { x: 100, y: 0 }, plugin: 'entity:save', label: 'Save' },
          { id: 'cond1', componentType: 5, position: { x: 200, y: 0 }, plugin: 'entity:is_new', label: 'Is New' },
          { id: 'gw1', componentType: 6, position: { x: 300, y: 0 }, plugin: 'gateway', label: 'Gateway' },
        ],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].type).toBe('start');
      expect(result.nodes[1].type).toBe('element');
      expect(result.nodes[2].type).toBe('link');
      expect(result.nodes[3].type).toBe('gateway');
    });

    it('should prefer explicit type over componentType', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'start', componentType: 4, position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].type).toBe('start');
    });

    it('should default to element when neither type nor componentType is present', () => {
      const data = {
        nodes: [{ id: 'node1', position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].type).toBe('element');
    });

    it('should filter out edges with non-existent source nodes', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 } }],
        edges: [{ id: 'edge1', source: 'nonexistent', target: 'node1' }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges).toHaveLength(0);
    });

    it('should filter out edges with non-existent target nodes', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 } }],
        edges: [{ id: 'edge1', source: 'node1', target: 'nonexistent' }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges).toHaveLength(0);
    });

    it('should promote a condition edge to a condition node with two default edges', () => {
      // Issue #3589093: conditions are promoted from edge properties to
      // first-class nodes internally. A single condition edge becomes a
      // condition node plus two plain ("default") split edges.
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{ id: 'edge1', source: 'node1', target: 'node2', condition: 'test_condition' }],
      } as any;

      const result = parseModelData(data);
      // A condition node is synthesized with a deterministic id.
      const condNode = result.nodes.find(n => n.id === 'cond__edge1');
      expect(condNode).toBeDefined();
      expect(condNode?.type).toBe('condition');
      expect(condNode?.data.plugin).toBe('test_condition');
      // The original condition edge is split into two default edges.
      expect(result.edges).toHaveLength(2);
      expect(result.edges.every(e => e.type === 'default')).toBe(true);
      const ids = result.edges.map(e => e.id).sort();
      // Inbound carries the original edge id (`<id>__in`) so demote can
      // recover it for fan-in; outbound is derived from the condition node id
      // (`<condNodeId>__out`) and is discarded on demote (issue #3589093).
      expect(ids).toEqual(['cond__edge1__out', 'edge1__in']);
    });

    it('should set default edge type for edges with annotation but no condition', () => {
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{ id: 'edge1', source: 'node1', target: 'node2', annotation: 'Some annotation' }],
      } as any;

      const result = parseModelData(data);
      // Annotations belong to conditions, not edges. An edge without a condition is always 'default'.
      expect(result.edges[0].type).toBe('default');
    });

    it('should set default edge type when backend sends empty condition fields', () => {
      // Regression: backend sends condition: "", conditionLabel: "", conditionConfiguration: {}
      // for edges without conditions. The empty object {} is truthy in JS, so the edge was
      // incorrectly classified as 'condition' type.
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{
          id: 'edge1',
          source: 'node1',
          target: 'node2',
          condition: '',
          conditionLabel: '',
          conditionConfiguration: {},
          annotation: '',
        }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges[0].type).toBe('default');
    });

    it('should promote to a condition node when conditionConfiguration has actual keys', () => {
      // Issue #3589093: an edge whose conditionConfiguration carries real
      // data is a condition edge and is promoted to a condition node.
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{
          id: 'edge1',
          source: 'node1',
          target: 'node2',
          condition: 'some_condition',
          conditionConfiguration: { permission: 'access content' },
        }],
      } as any;

      const result = parseModelData(data);
      const condNode = result.nodes.find(n => n.id === 'cond__edge1');
      expect(condNode).toBeDefined();
      expect(condNode?.type).toBe('condition');
      expect(condNode?.data.configuration).toEqual({ permission: 'access content' });
      // Original edge split into two default edges.
      expect(result.edges).toHaveLength(2);
      expect(result.edges.every(e => e.type === 'default')).toBe(true);
    });

    it('should set default edge type when only conditionConfiguration is empty object', () => {
      // Edge case: conditionConfiguration: {} alone should not make an edge a condition edge.
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{
          id: 'edge1',
          source: 'node1',
          target: 'node2',
          conditionConfiguration: {},
        }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges[0].type).toBe('default');
    });

    it('should assign output handle for gateway nodes (same as other node types)', () => {
      const data = {
        nodes: [
          { id: 'gateway1', type: 'gateway', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
          { id: 'node3', type: 'element', position: { x: 100, y: 100 } },
        ],
        edges: [
          { id: 'edge1', source: 'gateway1', target: 'node2' },
          { id: 'edge2', source: 'gateway1', target: 'node3' },
        ],
      } as any;

      const result = parseModelData(data);
      // All edges use the same 'output' handle regardless of source node type
      expect(result.edges[0].sourceHandle).toBe('output');
      expect(result.edges[1].sourceHandle).toBe('output');
    });

    it('should handle empty nodes and edges arrays', () => {
      const data = { nodes: [], edges: [] };
      const result = parseModelData(data);
      expect(result.nodes).toEqual([]);
      expect(result.edges).toEqual([]);
    });

    it('should handle missing nodes array', () => {
      const data = { edges: [] };
      const result = parseModelData(data as any);
      expect(result.nodes).toEqual([]);
    });

    it('should handle missing edges array', () => {
      const data = { nodes: [] };
      const result = parseModelData(data as any);
      expect(result.edges).toEqual([]);
    });

    it('should deduplicate edge IDs for parallel edges with the same source and target', () => {
      // Issue #3589093: these are condition edges, so each promotes to a
      // condition node + two split edges. Edge-ID deduplication still runs on
      // the raw edges BEFORE promotion, so the two synthesized condition nodes
      // receive distinct, deterministic ids derived from the deduplicated
      // original edge ids ('event1_action1' and 'event1_action1_1').
      const data = {
        nodes: [
          { id: 'event1', type: 'start', position: { x: 0, y: 0 } },
          { id: 'action1', type: 'element', position: { x: 200, y: 0 } },
        ],
        edges: [
          { id: 'event1_action1', source: 'event1', target: 'action1', condition: 'cond_a', conditionLabel: 'Cond A' },
          { id: 'event1_action1', source: 'event1', target: 'action1', condition: 'cond_b', conditionLabel: 'Cond B' },
        ],
      };
      const result = parseModelData(data as any);
      // Two condition nodes with distinct, deduplicated ids.
      const condNodes = result.nodes.filter(n => n.type === 'condition');
      expect(condNodes).toHaveLength(2);
      const condIds = condNodes.map(n => n.id).sort();
      expect(condIds).toEqual(['cond__event1_action1', 'cond__event1_action1_1']);
      expect(new Set(condIds).size).toBe(2);
      // Each condition edge split into two default edges -> four edges total.
      expect(result.edges).toHaveLength(4);
      const edgeIds = result.edges.map(e => e.id);
      expect(new Set(edgeIds).size).toBe(4);
    });

    it('should preserve distinct edge IDs without modification', () => {
      const data = {
        nodes: [
          { id: 'n1', type: 'start', position: { x: 0, y: 0 } },
          { id: 'n2', type: 'element', position: { x: 200, y: 0 } },
          { id: 'n3', type: 'element', position: { x: 400, y: 0 } },
        ],
        edges: [
          { id: 'n1_n2', source: 'n1', target: 'n2' },
          { id: 'n1_n3', source: 'n1', target: 'n3' },
        ],
      };
      const result = parseModelData(data as any);
      expect(result.edges.map(e => e.id)).toEqual(['n1_n2', 'n1_n3']);
    });
  });

  describe('exportModelData', () => {
    const mockNodes: Node[] = [
      {
        id: 'node1',
        type: 'element',
        position: { x: 100.5, y: 200.7 },
        data: {
          label: 'Test Node',
          plugin: 'test_plugin',
          configuration: { key: 'value' },
          componentType: 4,
          annotation: 'Test annotation',
        },
      },
    ];

    const mockEdges: Edge[] = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        sourceHandle: 'output',
        targetHandle: 'input',
        data: {
          condition: 'test_condition',
          conditionId: 'original_condition_id',
          conditionLabel: 'Test Condition',
          conditionConfiguration: { config: 'value' },
          annotation: 'Edge annotation',
          controlOffset: { x: 10, y: 20 },
        },
      },
    ];

    it('should export basic model structure', () => {
      const result = exportModelData([], []);
      expect(result.id).toBeDefined();
      expect(result.version).toBe('1.0.0');
      expect(result.metadata).toBeDefined();
      expect(result.nodes).toEqual([]);
      expect(result.edges).toEqual([]);
    });

    it('should export with custom metadata', () => {
      const metadata = {
        id: 'custom-id',
        label: 'Custom Model',
        documentation: 'A test model',
        tags: ['test', 'demo'],
      };

      const result = exportModelData([], [], metadata);
      expect(result.id).toBe('custom-id');
      expect(result.metadata.label).toBe('Custom Model');
      expect(result.metadata.documentation).toBe('A test model');
      expect(result.metadata.tags).toEqual(['test', 'demo']);
    });

    it('should round node positions to integers', () => {
      const result = exportModelData(mockNodes, []);
      expect(result.nodes[0].position.x).toBe(101);
      expect(result.nodes[0].position.y).toBe(201);
    });

    it('should include node properties in export', () => {
      const result = exportModelData(mockNodes, []);
      const exportedNode = result.nodes[0];

      expect(exportedNode.id).toBe('node1');
      expect(exportedNode.componentType).toBe(4);
      expect(exportedNode.plugin).toBe('test_plugin');
      expect(exportedNode.label).toBe('Test Node');
      expect(exportedNode.configuration).toEqual({ key: 'value' });
      expect(exportedNode.annotation).toBe('Test annotation');
    });

    it('should include edge properties in export', () => {
      const result = exportModelData([], mockEdges);
      const exportedEdge = result.edges[0];

      expect(exportedEdge.id).toBe('edge1');
      expect(exportedEdge.source).toBe('node1');
      expect(exportedEdge.target).toBe('node2');
      expect(exportedEdge.sourceHandle).toBe('output');
      expect(exportedEdge.targetHandle).toBe('input');
      expect(exportedEdge.condition).toBe('test_condition');
      expect(exportedEdge.conditionId).toBe('original_condition_id');
      expect(exportedEdge.conditionLabel).toBe('Test Condition');
      expect(exportedEdge.conditionConfiguration).toEqual({ config: 'value' });
      expect(exportedEdge.annotation).toBe('Edge annotation');
      expect(exportedEdge.controlOffset).toEqual({ x: 10, y: 20 });
    });

    it('should preserve conditionId through parse-export round-trip', () => {
      // Simulate data coming from the backend with an original condition ID
      const data = {
        nodes: [
          { id: 'event1', componentType: 1, position: { x: 0, y: 0 }, plugin: 'content_entity:insert', label: 'Insert' },
          { id: 'action1', componentType: 4, position: { x: 200, y: 0 }, plugin: 'entity:save', label: 'Save' },
        ],
        edges: [{
          id: 'event1_action1',
          source: 'event1',
          target: 'action1',
          condition: 'eca_entity_is_new',
          conditionId: 'eca_entity_is_new_10j5tps',
          conditionLabel: 'is new?',
          conditionConfiguration: { negate: false },
        }],
      } as any;

      // Parse (load into frontend). Issue #3589093: the condition is now
      // promoted to a condition node; conditionId lives on that node's data.
      const parsed = parseModelData(data);
      const condNode = parsed.nodes.find(n => n.type === 'condition');
      expect(condNode?.data.conditionId).toBe('eca_entity_is_new_10j5tps');

      // Export (save back to backend). The external JSON contract is
      // unchanged: conditionId is restored as an edge property.
      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'test' });
      expect(exported.edges[0].conditionId).toBe('eca_entity_is_new_10j5tps');
    });

    it('should export empty conditionId for new edges without original condition ID', () => {
      // When a user creates a new condition in the modeler, there is no
      // original conditionId — the backend generates one on save.
      const edges: Edge[] = [
        {
          id: 'edge_new',
          source: 'node1',
          target: 'node2',
          data: {
            condition: 'entity:is_new',
            conditionLabel: 'Is New',
            conditionConfiguration: { negate: false },
            // No conditionId — newly created in the frontend
          },
        },
      ];

      const result = exportModelData([], edges, { id: 'test' });
      expect(result.edges[0].condition).toBe('entity:is_new');
      expect(result.edges[0].conditionId).toBe('');
    });

    it('should preserve all condition data across a full model round-trip', () => {
      // Comprehensive model with multiple node types, conditions on different
      // edge sources (events, actions, gateways), annotations, and config.
      const fullModel = {
        id: 'round_trip_test',
        version: '1.0.0',
        metadata: {
          label: 'Round-trip Test',
          documentation: 'Tests full data preservation',
        },
        nodes: [
          { id: 'event_abc12345', componentType: 1, position: { x: 50, y: 100 }, plugin: 'content_entity:insert', label: 'Insert', configuration: { type: 'node article' } },
          { id: 'action_def67890', componentType: 4, position: { x: 300, y: 100 }, plugin: 'entity:save', label: 'Save', configuration: {} },
          { id: 'action_ghi11111', componentType: 4, position: { x: 550, y: 100 }, plugin: 'email:send', label: 'Send Email', configuration: { to: 'admin@example.com' } },
          { id: 'Gateway_12iwh1d', componentType: 6, position: { x: 300, y: 300 }, plugin: 'gateway', label: 'Check', configuration: {} },
        ],
        edges: [
          {
            id: 'event_abc12345_action_def67890',
            source: 'event_abc12345',
            target: 'action_def67890',
            condition: 'eca_entity_is_new',
            conditionId: 'eca_entity_is_new_10j5tps',
            conditionLabel: 'is new?',
            conditionConfiguration: { negate: false, entity: '' },
            annotation: 'Only new entities',
          },
          {
            id: 'action_def67890_action_ghi11111',
            source: 'action_def67890',
            target: 'action_ghi11111',
          },
          {
            id: 'Gateway_12iwh1d_action_ghi11111',
            source: 'Gateway_12iwh1d',
            target: 'action_ghi11111',
            condition: 'eca_count',
            conditionId: 'Flow_0h27nee',
            conditionLabel: '>0?',
            conditionConfiguration: { left: 'items', right: '0', operator: 'greaterthan' },
          },
        ],
      } as any;

      // Parse. Issue #3589093: the two condition edges are promoted to
      // condition nodes, so internally there are 4 original nodes + 2 condition
      // nodes = 6 nodes, and 2 condition edges (split into 4) + 1 plain edge = 5
      // edges. The condition data now lives on the synthesized condition nodes.
      const parsed = parseModelData(fullModel);
      expect(parsed.nodes).toHaveLength(6);
      expect(parsed.edges).toHaveLength(5);

      // Verify condition data survived parsing — now on the condition NODES.
      const condNode1 = parsed.nodes.find(n => n.id === 'cond__event_abc12345_action_def67890');
      expect(condNode1?.type).toBe('condition');
      expect(condNode1?.data.conditionId).toBe('eca_entity_is_new_10j5tps');
      expect(condNode1?.data.plugin).toBe('eca_entity_is_new');
      expect(condNode1?.data.annotation).toBe('Only new entities');

      const condNode2 = parsed.nodes.find(n => n.id === 'cond__Gateway_12iwh1d_action_ghi11111');
      expect(condNode2?.type).toBe('condition');
      expect(condNode2?.data.conditionId).toBe('Flow_0h27nee');
      expect(condNode2?.data.configuration).toEqual({ left: 'items', right: '0', operator: 'greaterthan' });

      // The plain (non-condition) edge is untouched: still a single default edge.
      const plainEdge = parsed.edges.find(e => e.id === 'action_def67890_action_ghi11111');
      expect(plainEdge?.type).toBe('default');

      // Export
      const exported = exportModelData(parsed.nodes, parsed.edges, {
        id: fullModel.id,
        label: fullModel.metadata.label,
      });

      // Verify all condition IDs are preserved
      const expEdge1 = exported.edges.find((e: any) => e.id === 'event_abc12345_action_def67890');
      expect(expEdge1?.conditionId).toBe('eca_entity_is_new_10j5tps');
      expect(expEdge1?.condition).toBe('eca_entity_is_new');
      expect(expEdge1?.conditionLabel).toBe('is new?');
      expect(expEdge1?.conditionConfiguration).toEqual({ negate: false, entity: '' });
      expect(expEdge1?.annotation).toBe('Only new entities');

      const expEdge2 = exported.edges.find((e: any) => e.id === 'Gateway_12iwh1d_action_ghi11111');
      expect(expEdge2?.conditionId).toBe('Flow_0h27nee');
      expect(expEdge2?.condition).toBe('eca_count');
      expect(expEdge2?.conditionLabel).toBe('>0?');

      // Plain edge should have empty conditionId
      const expPlain = exported.edges.find((e: any) => e.id === 'action_def67890_action_ghi11111');
      expect(expPlain?.conditionId).toBe('');
      expect(expPlain?.condition).toBe('');

      // Verify node data preserved
      const expEvent = exported.nodes.find((n: any) => n.id === 'event_abc12345');
      expect(expEvent?.plugin).toBe('content_entity:insert');
      expect(expEvent?.configuration).toEqual({ type: 'node article' });
      expect(expEvent?.position).toEqual({ x: 50, y: 100 });
    });

    it('should handle edges without data', () => {
      const edgesWithoutData: Edge[] = [
        { id: 'edge1', source: 'node1', target: 'node2' },
      ];

      const result = exportModelData([], edgesWithoutData);
      expect(result.edges[0].condition).toBe('');
    });

    it('should set executable to true by default', () => {
      const result = exportModelData([], []);
      expect(result.metadata.executable).toBe(true);
    });

    it('should allow executable to be set to false', () => {
      const result = exportModelData([], [], { executable: false });
      expect(result.metadata.executable).toBe(false);
    });

    it('should filter out internal properties starting with underscore from configuration', () => {
      const nodesWithInternalProps: Node[] = [
        {
          id: 'node1',
          type: 'element',
          position: { x: 100, y: 200 },
          data: {
            label: 'Test Node',
            plugin: 'test_plugin',
            configuration: {
              validKey: 'value',
              anotherKey: 123,
              _componentLabel: 'Internal Label',
              _internalState: 'something',
            },
          },
        },
      ];

      const result = exportModelData(nodesWithInternalProps, []);
      const exportedConfig = result.nodes[0].configuration;

      expect(exportedConfig.validKey).toBe('value');
      expect(exportedConfig.anotherKey).toBe(123);
      expect(exportedConfig._componentLabel).toBeUndefined();
      expect(exportedConfig._internalState).toBeUndefined();
    });

    it('should strip internal condition properties when re-saving a promoted condition', () => {
      const affectedModel = {
        id: 'affected-model',
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'event', label: 'Event', configuration: {} },
          { id: 'action', componentType: 4, position: { x: 200, y: 0 }, plugin: 'action', label: 'Action', configuration: {} },
        ],
        edges: [{
          id: 'event_action',
          source: 'event',
          target: 'action',
          condition: 'test_condition',
          conditionId: 'condition-id',
          conditionLabel: 'Renamed condition',
          conditionConfiguration: {
            negate: false,
            selection: '',
            _componentLabel: 'Stale internal label',
            _editorState: 'expanded',
          },
        }],
      };
      const parsed = parseModelData(JSON.stringify(affectedModel));
      const conditionNode = parsed.nodes.find(node => node.type === 'condition');

      expect(conditionNode).toBeDefined();
      if (!conditionNode) {
        throw new Error('Expected the condition edge to be promoted');
      }
      const inMemoryConfiguration = conditionNode.data.configuration;

      const result = exportModelData(parsed.nodes, parsed.edges, { id: affectedModel.id });

      expect(result.edges).toHaveLength(1);
      expect(result.edges[0].conditionLabel).toBe('Renamed condition');
      expect(result.edges[0].conditionConfiguration).toEqual({
        negate: false,
        selection: '',
      });
      expect(conditionNode.data.configuration).toBe(inMemoryConfiguration);
      expect(inMemoryConfiguration).toEqual({
        negate: false,
        selection: '',
        _componentLabel: 'Stale internal label',
        _editorState: 'expanded',
      });
    });

    it('should strip internal condition properties from direct edge exports', () => {
      const conditionConfiguration = {
        enabled: false,
        selection: '',
        _componentLabel: 'Internal label',
        _editorState: 'expanded',
      };
      const directEdge: Edge = {
        id: 'legacy-edge',
        source: 'event',
        target: 'action',
        data: {
          condition: 'test_condition',
          conditionLabel: 'Condition',
          conditionConfiguration,
        },
      };

      const result = exportModelData([], [directEdge]);

      expect(result.edges[0].conditionConfiguration).toEqual({
        enabled: false,
        selection: '',
      });
      expect(directEdge.data?.conditionConfiguration).toBe(conditionConfiguration);
      expect(conditionConfiguration).toEqual({
        enabled: false,
        selection: '',
        _componentLabel: 'Internal label',
        _editorState: 'expanded',
      });
    });

    it('should handle undefined configuration when filtering', () => {
      const nodesWithoutConfig: Node[] = [
        {
          id: 'node1',
          type: 'element',
          position: { x: 100, y: 200 },
          data: {
            label: 'Test Node',
            plugin: 'test_plugin',
          },
        },
      ];

      const result = exportModelData(nodesWithoutConfig, []);
      expect(result.nodes[0].configuration).toEqual({});
    });
  });

  describe('autoLayout', () => {
    it('should return null for null nodes', () => {
      expect(autoLayout(null as any, [])).toBeNull();
    });

    it('should return null for empty nodes array', () => {
      expect(autoLayout([], [])).toBeNull();
    });

    it('should handle undefined edges', () => {
      const nodes: Node[] = [
        { id: 'node1', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];

      const result = autoLayout(nodes, undefined as any);
      expect(result).toBeDefined();
    });

    it('should layout all nodes', () => {

      const nodes: Node[] = [
        { id: 'node1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];

      const edges: Edge[] = [
        { id: 'edge1', source: 'node1', target: 'node2' },
      ];

      const result = autoLayout(nodes, edges);
      expect(result).toBeDefined();
      expect(result?.length).toBe(2);
    });

    it('should place a linear chain in a single column (incremental contract)', () => {
      // Issue #3588454: auto-layout simulates incremental quick-add, so a
      // simple event → action → action chain must come back as a tidy
      // single-column layout — not as a fanned-out tree.
      const nodes: Node[] = [
        { id: 'a', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'b', type: 'element', position: { x: 0, y: 0 }, data: {} },
        { id: 'c', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];
      const edges: Edge[] = [
        { id: 'e1', source: 'a', target: 'b' },
        { id: 'e2', source: 'b', target: 'c' },
      ];
      const result = autoLayout(nodes, edges)!;
      const xs = new Set(result.map(n => n.position.x));
      expect(xs.size).toBe(1);
    });

    it('should NOT fan out non-gateway parents with multiple successors', () => {
      // Behavior change introduced by issue #3588454.  A plain element
      // node with two successors keeps them in its own column instead
      // of spreading them horizontally.
      const nodes: Node[] = [
        { id: 'a', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'b', type: 'element', position: { x: 0, y: 0 }, data: {} },
        { id: 'c', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];
      const edges: Edge[] = [
        { id: 'e1', source: 'a', target: 'b' },
        { id: 'e2', source: 'a', target: 'c' },
      ];
      const result = autoLayout(nodes, edges)!;
      const a = result.find(n => n.id === 'a')!;
      // No child is placed to the LEFT of the parent (which is what the
      // legacy auto-layout used to do for plain action parents).
      const childrenX = result.filter(n => n.id !== 'a').map(n => n.position.x);
      expect(childrenX.every(x => x >= a.position.x)).toBe(true);
    });

    it('should still fan out gateway successors horizontally', () => {
      const nodes: Node[] = [
        { id: 'a', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'g', type: 'gateway', position: { x: 0, y: 0 }, data: {} },
        { id: 'x', type: 'element', position: { x: 0, y: 0 }, data: {} },
        { id: 'y', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];
      const edges: Edge[] = [
        { id: 'e1', source: 'a', target: 'g' },
        { id: 'e2', source: 'g', target: 'x' },
        { id: 'e3', source: 'g', target: 'y' },
      ];
      const result = autoLayout(nodes, edges)!;
      const x = result.find(n => n.id === 'x')!;
      const y = result.find(n => n.id === 'y')!;
      // Gateway children get distinct X positions (horizontal fan-out).
      expect(x.position.x).not.toBe(y.position.x);
    });
  });

  describe('getFitViewport', () => {
    it('should return default viewport for empty nodes', () => {
      const result = getFitViewport([], 800, 600);
      expect(result).toEqual({ x: 0, y: 0, zoom: 1 });
    });

    it('should calculate viewport to fit nodes', () => {
      const nodes: Node[] = [
        { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', position: { x: 1000, y: 800 }, data: {} },
      ];

      const result = getFitViewport(nodes, 800, 600);
      expect(result.zoom).toBeLessThanOrEqual(4);
      expect(result.zoom).toBeGreaterThan(0);
    });

    it('should respect max zoom limit', () => {
      const nodes: Node[] = [
        { id: 'node1', position: { x: 100, y: 100 }, data: {} },
      ];

      // Small nodes in large viewport should trigger max zoom
      const result = getFitViewport(nodes, 8000, 6000);
      expect(result.zoom).toBeLessThanOrEqual(4);
    });

    it('should apply padding factor', () => {
      const nodes: Node[] = [
        { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', position: { x: 400, y: 300 }, data: {} },
      ];

      const withPadding = getFitViewport(nodes, 800, 600, 0.2);
      const withoutPadding = getFitViewport(nodes, 800, 600, 0);

      // With padding should have lower zoom to fit more
      expect(withPadding.zoom).toBeLessThanOrEqual(withoutPadding.zoom);
    });

    it('should internally compute node bounds for viewport calculation', () => {
      // getNodesBounds is now private, but we can verify its behavior
      // through getFitViewport by checking that spread-out nodes produce
      // a lower zoom than tightly grouped nodes
      const spreadNodes: Node[] = [
        { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', position: { x: 2000, y: 1500 }, data: {} },
      ];

      const tightNodes: Node[] = [
        { id: 'node1', position: { x: 100, y: 100 }, data: {} },
        { id: 'node2', position: { x: 150, y: 150 }, data: {} },
      ];

      const spreadResult = getFitViewport(spreadNodes, 800, 600);
      const tightResult = getFitViewport(tightNodes, 800, 600);

      expect(spreadResult.zoom).toBeLessThan(tightResult.zoom);
    });
  });

  describe('condition node translation (round-trip)', () => {
    // Issue #3589093: conditions are promoted from edge properties to
    // first-class condition NODES internally, but the backend JSON contract is
    // unchanged — conditions remain edge properties on export. These tests
    // assert that exportModelData(parseModelData(J)) reproduces the condition
    // edge slice of J byte-for-byte (no-edit case), and that conditions never
    // leak into the exported nodes[] array.

    /**
     * Export only the condition-relevant fields of an edge, in the order the
     * backend JSON uses them, so deep-equal comparisons ignore the extra
     * sourceHandle/targetHandle keys the exporter always adds.
     */
    const conditionSlice = (edge: any) => ({
      id: edge.id,
      source: edge.source,
      target: edge.target,
      condition: edge.condition,
      conditionId: edge.conditionId,
      conditionLabel: edge.conditionLabel,
      conditionConfiguration: edge.conditionConfiguration,
      annotation: edge.annotation,
      controlOffset: edge.controlOffset,
    });

    it('case 1: single condition edge round-trips to identical condition fields', () => {
      const json = {
        id: 'm1',
        metadata: { label: 'M1' },
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'content_entity:insert', label: 'Insert', configuration: {} },
          { id: 'action', componentType: 4, position: { x: 200, y: 0 }, plugin: 'entity:save', label: 'Save', configuration: {} },
        ],
        edges: [
          {
            id: 'event_action',
            source: 'event',
            target: 'action',
            condition: 'eca_entity_is_new',
            conditionId: 'eca_entity_is_new_abc123',
            conditionLabel: 'is new?',
            conditionConfiguration: { negate: false },
            annotation: 'only new',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      // 3 nodes (event, action, condition), 2 split edges.
      expect(parsed.nodes).toHaveLength(3);
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(1);
      expect(parsed.edges).toHaveLength(2);
      expect(parsed.edges.every(e => e.type === 'default')).toBe(true);

      const exported = exportModelData(parsed.nodes, parsed.edges, json.metadata);
      // Back to exactly 2 nodes + 1 condition edge.
      expect(exported.nodes).toHaveLength(2);
      expect(exported.edges).toHaveLength(1);

      expect(conditionSlice(exported.edges[0])).toEqual(conditionSlice(json.edges[0]));
    });

    it('case 2: no-edit save yields an identical conditionId', () => {
      const json = {
        metadata: { id: 'm2' },
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'p', label: 'E', configuration: {} },
          { id: 'action', componentType: 4, position: { x: 200, y: 0 }, plugin: 'q', label: 'A', configuration: {} },
        ],
        edges: [
          {
            id: 'event_action',
            source: 'event',
            target: 'action',
            condition: 'cond_x',
            conditionId: 'stable_cond_id_xyz',
            conditionLabel: 'X?',
            conditionConfiguration: { a: 1 },
            annotation: '',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'm2' });
      // conditionId must be byte-for-byte the same id that came in.
      expect(exported.edges[0].conditionId).toBe('stable_cond_id_xyz');
    });

    it('case 3: parallel successors with two conditions restore both edges', () => {
      const json = {
        metadata: { id: 'm3' },
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'p', label: 'E', configuration: {} },
          { id: 'action', componentType: 4, position: { x: 200, y: 0 }, plugin: 'q', label: 'A', configuration: {} },
        ],
        edges: [
          {
            id: 'edge_a',
            source: 'event',
            target: 'action',
            condition: 'cond_a',
            conditionId: 'cid_a',
            conditionLabel: 'A?',
            conditionConfiguration: { k: 'a' },
            annotation: '',
            controlOffset: { x: 0, y: 0 },
          },
          {
            id: 'edge_b',
            source: 'event',
            target: 'action',
            condition: 'cond_b',
            conditionId: 'cid_b',
            conditionLabel: 'B?',
            conditionConfiguration: { k: 'b' },
            annotation: '',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      // Two condition nodes synthesized.
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(2);
      // Four split edges.
      expect(parsed.edges).toHaveLength(4);

      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'm3' });
      expect(exported.edges).toHaveLength(2);

      const expA = exported.edges.find((e: any) => e.id === 'edge_a');
      const expB = exported.edges.find((e: any) => e.id === 'edge_b');
      expect(conditionSlice(expA)).toEqual(conditionSlice(json.edges[0]));
      expect(conditionSlice(expB)).toEqual(conditionSlice(json.edges[1]));
    });

    it('case 4: new condition without conditionId gets a UUID, preserves data', () => {
      const json = {
        metadata: { id: 'm4' },
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'p', label: 'E', configuration: {} },
          { id: 'action', componentType: 4, position: { x: 200, y: 0 }, plugin: 'q', label: 'A', configuration: {} },
        ],
        edges: [
          {
            id: 'event_action',
            source: 'event',
            target: 'action',
            condition: 'brand_new_cond',
            conditionId: '', // new condition — backend has not assigned an id
            conditionLabel: 'New?',
            conditionConfiguration: { flag: true },
            annotation: '',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'm4' });

      // A non-empty conditionId is generated (mocked uuid -> deterministic).
      expect(exported.edges[0].conditionId).toBe('mocked-uuid-v4');
      expect(exported.edges[0].conditionId).not.toBe('');
      // Condition / label / config are preserved.
      expect(exported.edges[0].condition).toBe('brand_new_cond');
      expect(exported.edges[0].conditionLabel).toBe('New?');
      expect(exported.edges[0].conditionConfiguration).toEqual({ flag: true });
    });

    it('case 5: mixed model — plain edge untouched, condition edge round-trips', () => {
      const json = {
        metadata: { id: 'm5' },
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'p', label: 'E', configuration: {} },
          { id: 'a1', componentType: 4, position: { x: 200, y: 0 }, plugin: 'q', label: 'A1', configuration: {} },
          { id: 'a2', componentType: 4, position: { x: 400, y: 0 }, plugin: 'r', label: 'A2', configuration: {} },
        ],
        edges: [
          {
            id: 'event_a1',
            source: 'event',
            target: 'a1',
            condition: 'cond_m',
            conditionId: 'cid_m',
            conditionLabel: 'M?',
            conditionConfiguration: { m: 1 },
            annotation: 'note',
            controlOffset: { x: 0, y: 0 },
          },
          {
            id: 'a1_a2',
            source: 'a1',
            target: 'a2',
            condition: '',
            conditionId: '',
            conditionLabel: '',
            conditionConfiguration: {},
            annotation: '',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      // One condition node; plain edge stays single, condition edge splits.
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(1);
      const plainParsed = parsed.edges.find(e => e.id === 'a1_a2');
      expect(plainParsed).toBeDefined();
      expect(plainParsed?.type).toBe('default');

      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'm5' });
      expect(exported.edges).toHaveLength(2);

      // Plain edge round-trips unchanged.
      const expPlain = exported.edges.find((e: any) => e.id === 'a1_a2');
      expect(conditionSlice(expPlain)).toEqual(conditionSlice(json.edges[1]));

      // Condition edge round-trips unchanged.
      const expCond = exported.edges.find((e: any) => e.id === 'event_a1');
      expect(conditionSlice(expCond)).toEqual(conditionSlice(json.edges[0]));
    });

    it('case 6: conditions never appear in exported nodes[]', () => {
      const json = {
        metadata: { id: 'm6' },
        nodes: [
          { id: 'event', componentType: 1, position: { x: 0, y: 0 }, plugin: 'p', label: 'E', configuration: {} },
          { id: 'action', componentType: 4, position: { x: 200, y: 0 }, plugin: 'q', label: 'A', configuration: {} },
        ],
        edges: [
          {
            id: 'event_action',
            source: 'event',
            target: 'action',
            condition: 'cond_z',
            conditionId: 'cid_z',
            conditionLabel: 'Z?',
            conditionConfiguration: { z: 1 },
            annotation: '',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'm6' });

      // No exported node is a condition (no componentType 5, no
      // __isConditionNode marker, no type 'condition').
      exported.nodes.forEach((n: any) => {
        expect(n.componentType).not.toBe(5);
        expect(n.__isConditionNode).toBeUndefined();
        expect(n.type).not.toBe('condition');
      });
      // And the condition still serializes as an edge property.
      expect(exported.edges[0].condition).toBe('cond_z');
      expect(exported.edges[0].conditionId).toBe('cid_z');
    });

    // ── Context-gated condition reuse (issue #3589093, Tasks 2 & 3) ─────────
    // A reused condition is N backend edges (A->Z, B->Z) sharing the SAME
    // non-empty conditionId AND target.  With grouping ON they promote to ONE
    // shared condition node (N inbound + 1 outbound); demote inverts losslessly.

    const reuseJson = () => ({
      metadata: { id: 'reuse' },
      nodes: [
        { id: 'a', componentType: 4, position: { x: 0, y: 0 }, plugin: 'pa', label: 'A', configuration: {} },
        { id: 'b', componentType: 4, position: { x: 0, y: 100 }, plugin: 'pb', label: 'B', configuration: {} },
        { id: 'z', componentType: 4, position: { x: 200, y: 50 }, plugin: 'pz', label: 'Z', configuration: {} },
      ],
      edges: [
        {
          id: 'a_z',
          source: 'a',
          target: 'z',
          condition: 'shared_cond',
          conditionId: 'shared_cid',
          conditionLabel: 'Shared?',
          conditionConfiguration: { k: 1 },
          annotation: 'note',
          controlOffset: { x: 0, y: 0 },
        },
        {
          id: 'b_z',
          source: 'b',
          target: 'z',
          condition: 'shared_cond',
          conditionId: 'shared_cid',
          conditionLabel: 'Shared?',
          conditionConfiguration: { k: 1 },
          annotation: 'note',
          controlOffset: { x: 0, y: 0 },
        },
      ],
    } as any);

    it('reuse OFF (default): two condition edges sharing conditionId → two separate condition nodes with DE-DUPLICATED ids', () => {
      // Issue #3589100: with reuse OFF, two condition edges that share the same
      // non-empty conditionId must NOT both re-emit that id on export (which
      // would break diverging config on save).  The first edge keeps the id;
      // the second receives a fresh, distinct id.
      const json = reuseJson();
      // No options → grouping disabled (defensive default).
      const parsed = parseModelData(json);
      // Two independent condition nodes (one per edge), four split edges.
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(2);
      expect(parsed.edges).toHaveLength(4);

      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'reuse' });
      // Still exactly two condition edges.
      expect(exported.edges).toHaveLength(2);
      const expA = exported.edges.find((e: any) => e.id === 'a_z') as any;
      const expB = exported.edges.find((e: any) => e.id === 'b_z') as any;
      expect(expA).toBeDefined();
      expect(expB).toBeDefined();

      // The FIRST edge (a_z) keeps the original conditionId unchanged.
      expect(expA.conditionId).toBe('shared_cid');
      // The SECOND edge (b_z) has a DIFFERENT, non-empty conditionId.
      expect(expB.conditionId).toBeTruthy();
      expect(expB.conditionId).not.toBe('shared_cid');
      // The two exported conditionIds are not equal to each other.
      expect(expA.conditionId).not.toBe(expB.conditionId);

      // Every OTHER condition field still round-trips for both edges; only the
      // conditionId diverges, so compare a slice that excludes it.
      const sliceNoId = (edge: any) => {
        const { conditionId: _conditionId, ...rest } = conditionSlice(edge);
        return rest;
      };
      expect(sliceNoId(expA)).toEqual(sliceNoId(json.edges[0]));
      expect(sliceNoId(expB)).toEqual(sliceNoId(json.edges[1]));
    });

    it('reuse OFF: same conditionId but DIFFERENT targets still diverge (cross-target dedup)', () => {
      // Issue #3589100: de-duplication is keyed on conditionId ALONE, so two
      // condition edges sharing a conditionId but pointing at different targets
      // must STILL receive distinct ids on export.
      const json = {
        metadata: { id: 'reuse_cross_target' },
        nodes: [
          { id: 'a', componentType: 4, position: { x: 0, y: 0 }, plugin: 'pa', label: 'A', configuration: {} },
          { id: 'b', componentType: 4, position: { x: 0, y: 100 }, plugin: 'pb', label: 'B', configuration: {} },
          { id: 'y', componentType: 4, position: { x: 200, y: 0 }, plugin: 'py', label: 'Y', configuration: {} },
          { id: 'z', componentType: 4, position: { x: 200, y: 100 }, plugin: 'pz', label: 'Z', configuration: {} },
        ],
        edges: [
          { id: 'a_y', source: 'a', target: 'y', condition: 'c', conditionId: 'cid', conditionLabel: 'C?', conditionConfiguration: {}, annotation: '', controlOffset: { x: 0, y: 0 } },
          { id: 'b_z', source: 'b', target: 'z', condition: 'c', conditionId: 'cid', conditionLabel: 'C?', conditionConfiguration: {}, annotation: '', controlOffset: { x: 0, y: 0 } },
        ],
      } as any;

      // Reuse OFF (default).
      const parsed = parseModelData(json);
      // One condition node per condition edge.
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(2);

      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'reuse_cross_target' });
      expect(exported.edges).toHaveLength(2);
      const expAY = exported.edges.find((e: any) => e.id === 'a_y') as any;
      const expBZ = exported.edges.find((e: any) => e.id === 'b_z') as any;
      expect(expAY).toBeDefined();
      expect(expBZ).toBeDefined();
      // First occurrence keeps the id, the second diverges — even though their
      // targets differ.
      expect(expAY.conditionId).toBe('cid');
      expect(expBZ.conditionId).toBeTruthy();
      expect(expBZ.conditionId).not.toBe('cid');
      expect(expAY.conditionId).not.toBe(expBZ.conditionId);
    });

    it('reuse OFF: a SINGLE condition edge with a conditionId is left completely unchanged on round-trip (no spurious dedup)', () => {
      const json = {
        metadata: { id: 'single_cid' },
        nodes: [
          { id: 'a', componentType: 4, position: { x: 0, y: 0 }, plugin: 'pa', label: 'A', configuration: {} },
          { id: 'z', componentType: 4, position: { x: 200, y: 0 }, plugin: 'pz', label: 'Z', configuration: {} },
        ],
        edges: [
          {
            id: 'a_z',
            source: 'a',
            target: 'z',
            condition: 'lonely_cond',
            conditionId: 'lonely_cid',
            conditionLabel: 'Lonely?',
            conditionConfiguration: { k: 9 },
            annotation: 'solo',
            controlOffset: { x: 0, y: 0 },
          },
        ],
      } as any;

      const parsed = parseModelData(json);
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(1);

      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'single_cid' });
      expect(exported.edges).toHaveLength(1);
      const expA = exported.edges.find((e: any) => e.id === 'a_z') as any;
      expect(expA).toBeDefined();
      // No spurious dedup: the lone conditionId is preserved byte-for-byte and
      // the whole condition slice round-trips unchanged.
      expect(expA.conditionId).toBe('lonely_cid');
      expect(conditionSlice(expA)).toEqual(conditionSlice(json.edges[0]));
    });

    it('reuse ON: two condition edges sharing conditionId+target collapse into ONE shared node (2 inbound + 1 outbound)', () => {
      const json = reuseJson();
      const parsed = parseModelData(json, { allowConditionReuse: true });
      // ONE shared condition node.
      const condNodes = parsed.nodes.filter(n => n.type === 'condition');
      expect(condNodes).toHaveLength(1);
      const sharedId = condNodes[0].id;
      // Two inbound edges (a->shared, b->shared) + one outbound (shared->z).
      const inbound = parsed.edges.filter(e => e.target === sharedId);
      const outbound = parsed.edges.filter(e => e.source === sharedId);
      expect(inbound).toHaveLength(2);
      expect(outbound).toHaveLength(1);
      expect(outbound[0].target).toBe('z');
      expect(inbound.map(e => e.source).sort()).toEqual(['a', 'b']);
    });

    it('reuse ON: round-trip is LOSSLESS — shared node demotes back to two edges sharing conditionId+target', () => {
      const json = reuseJson();
      const parsed = parseModelData(json, { allowConditionReuse: true });
      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'reuse' });

      // Back to exactly two condition edges (NO inbound edge dropped).
      expect(exported.edges).toHaveLength(2);
      const expA = exported.edges.find((e: any) => e.id === 'a_z') as any;
      const expB = exported.edges.find((e: any) => e.id === 'b_z') as any;
      // Original backend edge ids recovered for both inbound edges.
      expect(expA).toBeDefined();
      expect(expB).toBeDefined();
      // Both share the same conditionId and target (reused identity preserved).
      expect(expA.conditionId).toBe('shared_cid');
      expect(expB.conditionId).toBe('shared_cid');
      expect(expA.target).toBe('z');
      expect(expB.target).toBe('z');
      // Full condition slice round-trips byte-for-byte for both edges.
      expect(conditionSlice(expA)).toEqual(conditionSlice(json.edges[0]));
      expect(conditionSlice(expB)).toEqual(conditionSlice(json.edges[1]));
    });

    it('reuse ON: empty conditionId is never grouped (stays one node per edge)', () => {
      const json = {
        metadata: { id: 'reuse_empty' },
        nodes: [
          { id: 'a', componentType: 4, position: { x: 0, y: 0 }, plugin: 'pa', label: 'A', configuration: {} },
          { id: 'b', componentType: 4, position: { x: 0, y: 100 }, plugin: 'pb', label: 'B', configuration: {} },
          { id: 'z', componentType: 4, position: { x: 200, y: 50 }, plugin: 'pz', label: 'Z', configuration: {} },
        ],
        edges: [
          { id: 'a_z', source: 'a', target: 'z', condition: 'c', conditionId: '', conditionLabel: 'C?', conditionConfiguration: {}, annotation: '', controlOffset: { x: 0, y: 0 } },
          { id: 'b_z', source: 'b', target: 'z', condition: 'c', conditionId: '', conditionLabel: 'C?', conditionConfiguration: {}, annotation: '', controlOffset: { x: 0, y: 0 } },
        ],
      } as any;
      const parsed = parseModelData(json, { allowConditionReuse: true });
      // Empty conditionId is excluded from grouping → two separate nodes.
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(2);
    });

    it('reuse ON: same conditionId but DIFFERENT target are NOT grouped', () => {
      const json = {
        metadata: { id: 'reuse_diff_target' },
        nodes: [
          { id: 'a', componentType: 4, position: { x: 0, y: 0 }, plugin: 'pa', label: 'A', configuration: {} },
          { id: 'b', componentType: 4, position: { x: 0, y: 100 }, plugin: 'pb', label: 'B', configuration: {} },
          { id: 'y', componentType: 4, position: { x: 200, y: 0 }, plugin: 'py', label: 'Y', configuration: {} },
          { id: 'z', componentType: 4, position: { x: 200, y: 100 }, plugin: 'pz', label: 'Z', configuration: {} },
        ],
        edges: [
          { id: 'a_y', source: 'a', target: 'y', condition: 'c', conditionId: 'cid', conditionLabel: 'C?', conditionConfiguration: {}, annotation: '', controlOffset: { x: 0, y: 0 } },
          { id: 'b_z', source: 'b', target: 'z', condition: 'c', conditionId: 'cid', conditionLabel: 'C?', conditionConfiguration: {}, annotation: '', controlOffset: { x: 0, y: 0 } },
        ],
      } as any;
      const parsed = parseModelData(json, { allowConditionReuse: true });
      // Different targets ⇒ not the same reused condition ⇒ two nodes.
      expect(parsed.nodes.filter(n => n.type === 'condition')).toHaveLength(2);
    });
  });

  describe('isConditionReuseEnabled', () => {
    it('returns false when constraints are undefined (defensive default)', () => {
      expect(isConditionReuseEnabled(undefined)).toBe(false);
    });

    it('returns false when no successor constraint opts in', () => {
      expect(isConditionReuseEnabled({ start: { successors: { max: 3 } } } as any)).toBe(false);
    });

    it('returns false when the flag is explicitly false', () => {
      expect(isConditionReuseEnabled({ start: { successors: { allowConditionReuse: false } } } as any)).toBe(false);
    });

    it('returns true when ANY successor constraint sets allowConditionReuse', () => {
      expect(isConditionReuseEnabled({
        start: { successors: { max: 3 } },
        element: { successors: { allowConditionReuse: true } },
      } as any)).toBe(true);
    });
  });

  // ── Defensive demote for non-1-in/1-out condition nodes (fix C5) ──────────
  // The 1-in/1-out invariant is guaranteed by the connect/delete guards, but
  // demoteConditionNodes must NEVER emit a condition node (componentType 5)
  // into the exported nodes[] even if a broken graph slips through.
  describe('exportModelData defensive condition demotion (C5)', () => {
    const conditionNode = (id: string): Node => ({
      id,
      type: 'condition',
      position: { x: 0, y: 0 },
      data: {
        __isConditionNode: true,
        componentType: 5,
        plugin: 'cond_plugin',
        label: 'Broken',
        configuration: {},
      },
    } as any);

    const plainNode = (id: string): Node => ({
      id,
      type: 'element',
      position: { x: 0, y: 0 },
      data: { componentType: 4, plugin: 'p', label: id, configuration: {} },
    } as any);

    it('demotes a condition node with N inbound + 1 outbound to N edges WITHOUT data loss (reuse)', () => {
      // CORRECTED cardinality (issue #3589093): N-inbound + 1-outbound is a
      // VALID reused condition, NOT a broken graph.  Demote must emit one
      // backend edge PER inbound edge (no inbound edge dropped), all sharing
      // the SAME conditionId.  (This supersedes the old data-dropping
      // "collapse using the first pair" behavior, which lost edges.)
      const nodes: Node[] = [plainNode('p1'), plainNode('p2'), conditionNode('cond'), plainNode('succ')];
      const edges: Edge[] = [
        { id: 'in1', source: 'p1', target: 'cond', type: 'default', data: {} },
        { id: 'in2', source: 'p2', target: 'cond', type: 'default', data: {} },
        { id: 'out', source: 'cond', target: 'succ', type: 'default', data: {} },
      ];

      const exported = exportModelData(nodes, edges, { id: 'reuse1' });

      // No condition node leaks into nodes[].
      exported.nodes.forEach((n: any) => {
        expect(n.componentType).not.toBe(5);
        expect(n.type).not.toBe('condition');
      });
      // TWO collapsed condition edges — one per inbound edge, NO data loss.
      const condEdges = exported.edges.filter((e: any) => e.condition === 'cond_plugin');
      expect(condEdges).toHaveLength(2);
      const bySource = (s: string): any => condEdges.find((e: any) => e.source === s);
      const e1 = bySource('p1');
      const e2 = bySource('p2');
      expect(e1).toBeDefined();
      expect(e2).toBeDefined();
      expect(e1.target).toBe('succ');
      expect(e2.target).toBe('succ');
      // Both edges share the SAME conditionId (reused condition identity).
      expect(e1.conditionId).toBe(e2.conditionId);
      expect(e1.conditionId).toBeTruthy();
      // No edge still references the removed condition node.
      expect(exported.edges.find((e: any) => e.target === 'cond')).toBeUndefined();
      expect(exported.edges.find((e: any) => e.source === 'cond')).toBeUndefined();
    });

    it('drops a dangling condition node (no inbound) entirely — never emits componentType 5', () => {
      const nodes: Node[] = [conditionNode('cond'), plainNode('succ')];
      const edges: Edge[] = [
        { id: 'out', source: 'cond', target: 'succ', type: 'default', data: {} },
      ];

      const exported = exportModelData(nodes, edges, { id: 'broken2' });

      exported.nodes.forEach((n: any) => {
        expect(n.componentType).not.toBe(5);
        expect(n.type).not.toBe('condition');
      });
      // The stray edge to/from the dropped condition node is gone.
      expect(exported.edges.find((e: any) => e.source === 'cond' || e.target === 'cond')).toBeUndefined();
      // The surviving plain node remains.
      expect(exported.nodes.find((n: any) => n.id === 'succ')).toBeDefined();
    });

    it('drops a fully orphaned condition node (no edges) — never emits componentType 5', () => {
      const nodes: Node[] = [conditionNode('cond'), plainNode('keep')];
      const edges: Edge[] = [];

      const exported = exportModelData(nodes, edges, { id: 'broken3' });

      expect(exported.nodes.find((n: any) => n.id === 'cond')).toBeUndefined();
      expect(exported.nodes.find((n: any) => n.id === 'keep')).toBeDefined();
      exported.nodes.forEach((n: any) => {
        expect(n.componentType).not.toBe(5);
      });
    });
  });
});
