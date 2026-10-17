import { renderHook } from '@testing-library/react';
import { useReplayIndicators } from '../useReplayIndicators';

describe('useReplayIndicators', () => {
  let mockNodes: any[];
  let mockEdges: any[];

  beforeEach(() => {
    // CHANGED (node model, issue #3589093): conditions are NODES now.  A
    // condition between node-1 and node-2 is modeled as a condition NODE
    // (cond-node) sitting between them, with plugin id 'condition-1'.  The
    // indicator attaches to the condition node's position.
    mockNodes = [
      { id: 'node-1', position: { x: 100, y: 100 }, width: 200, height: 100 },
      {
        id: 'cond-node',
        type: 'condition',
        position: { x: 250, y: 100 },
        width: 200,
        height: 100,
        data: { plugin: 'condition-1', conditionId: 'rt-1', __isConditionNode: true },
      },
      { id: 'node-2', position: { x: 400, y: 100 }, width: 200, height: 100 },
    ];
    // Edges no longer carry conditions; they are plain edges routing through
    // the condition node.  Kept here because the hook still accepts an edges
    // prop, but they are not used for condition lookup anymore.
    mockEdges = [
      { id: 'edge-in', source: 'node-1', target: 'cond-node', data: {} },
      { id: 'edge-out', source: 'cond-node', target: 'node-2', data: {} },
    ];
  });

  const renderUseReplayIndicators = (props = {}) => {
    return renderHook(() =>
      useReplayIndicators({
        isReplayMode: false,
        currentReplayStep: -1,
        replayData: null,
        edges: mockEdges,
        nodes: mockNodes,
        ...props,
      })
    );
  };

  describe('initial state', () => {
    it('should return empty replayIndicators array', () => {
      const { result } = renderUseReplayIndicators();

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('when not in replay mode', () => {
    it('should not create indicators when isReplayMode is false', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: false,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('when currentReplayStep is invalid', () => {
    it('should not create indicators when step is negative', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: -1,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    it('should not create indicators when step exceeds data length', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 5,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('when replayData is null', () => {
    it('should not create indicators', () => {
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData: null,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('condition result indicators', () => {
    it('should create indicator for add successor step with condition', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
      expect(result.current.replayIndicators[0].id).toBe('condition-result-condition-1');
      expect(result.current.replayIndicators[0].color).toBe('var(--modeler-color-success)'); // Green for passed
    });

    it('should create red indicator for ignore successor step', () => {
      const replayData = [
        { type: 'ignore successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
      expect(result.current.replayIndicators[0].color).toBe('var(--modeler-color-danger-soft)'); // Red for failed
    });

    it('should find condition node by data.conditionId fallback', () => {
      // CHANGED (node model): match against the node's backend round-trip
      // conditionId when the plugin id does not match.
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'rt-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
    });

    it('should not create indicator if condition node not found', () => {
      // CHANGED (node model): no condition node present at all.
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 100 } },
        { id: 'node-2', position: { x: 400, y: 100 } },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    // ---- ECA condition CONFIG id is the primary match (issue #3589108) ----
    // `step.conditionId` is ECA's condition config id, round-tripped by the
    // backend as `edge.conditionId` and copied onto the node as
    // `data.conditionId`. It must win over the legacy `data.plugin` match.
    describe('matching by the ECA condition config id', () => {
      beforeEach(() => {
        mockNodes = [
          { id: 'node-1', position: { x: 100, y: 100 }, width: 200, height: 100 },
          {
            id: 'cond-wrong',
            type: 'condition',
            position: { x: 0, y: 0 },
            width: 200,
            height: 100,
            // Its PLUGIN id collides with the step's conditionId — must lose.
            data: { plugin: 'eca-config-uuid', conditionId: 'other-cfg', __isConditionNode: true },
          },
          {
            id: 'cond-right',
            type: 'condition',
            position: { x: 250, y: 100 },
            width: 200,
            height: 100,
            data: { plugin: 'eca_scalar', conditionId: 'eca-config-uuid', __isConditionNode: true },
          },
          { id: 'node-2', position: { x: 400, y: 100 }, width: 200, height: 100 },
        ];
      });

      it('should place a GREEN indicator on the config-id match for "add successor"', () => {
        const replayData = [
          { type: 'add successor', id: 'node-1', conditionId: 'eca-config-uuid', successorId: 'node-2' },
        ];
        const { result } = renderUseReplayIndicators({
          isReplayMode: true,
          currentReplayStep: 0,
          replayData,
        });

        expect(result.current.replayIndicators.length).toBe(1);
        expect(result.current.replayIndicators[0].color).toBe('var(--modeler-color-success)');
        // cond-right: x=250 width=200 -> 350; y=100 -> 80. (cond-wrong is at 0/0.)
        expect(result.current.replayIndicators[0].x).toBe(350);
        expect(result.current.replayIndicators[0].y).toBe(80);
      });

      it('should place a RED indicator on the config-id match for "ignore successor"', () => {
        const replayData = [
          { type: 'ignore successor', id: 'node-1', conditionId: 'eca-config-uuid', successorId: 'node-2' },
        ];
        const { result } = renderUseReplayIndicators({
          isReplayMode: true,
          currentReplayStep: 0,
          replayData,
        });

        expect(result.current.replayIndicators.length).toBe(1);
        expect(result.current.replayIndicators[0].color).toBe('var(--modeler-color-danger-soft)');
        expect(result.current.replayIndicators[0].x).toBe(350);
        expect(result.current.replayIndicators[0].y).toBe(80);
      });
    });

    it('should not create indicator if conditionId matches no node', () => {
      // CHANGED (node model): condition node exists but neither its plugin
      // nor its conditionId matches the step.
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'non-matching', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('indicator positioning', () => {
    it('should calculate indicator position above the condition node center', () => {
      // CHANGED (node model): the indicator attaches to the condition NODE.
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      const indicator = result.current.replayIndicators[0];
      // cond-node: x=250, width=200 -> center X = 350; y=100 -> 100 - 20 = 80.
      expect(indicator.x).toBe(350);
      expect(indicator.y).toBe(80);
    });

    it('should return flow coordinates (not screen coordinates)', () => {
      // Positions should be in flow coordinates — the rendering layer
      // (EdgeLabelRenderer) handles the viewport transform automatically.
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      const indicator = result.current.replayIndicators[0];
      // cond-node center X = 350; y = 100 - 20 = 80. Flow coordinates.
      expect(indicator.x).toBe(350);
      expect(indicator.y).toBe(80);
    });

    it('should derive position purely from the condition node coordinates', () => {
      // CHANGED (node model): nodes carry no control offset; the indicator
      // position derives from the condition node's own coordinates.
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 100 }, width: 200, height: 100 },
        {
          id: 'cond-node',
          type: 'condition',
          position: { x: 300, y: 250 },
          width: 100,
          height: 60,
          data: { plugin: 'condition-1', conditionId: 'rt-1', __isConditionNode: true },
        },
        { id: 'node-2', position: { x: 400, y: 100 }, width: 200, height: 100 },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      const indicator = result.current.replayIndicators[0];
      // cond-node: x=300, width=100 -> center X = 350; y=250 -> 250 - 20 = 230.
      expect(indicator.x).toBe(350);
      expect(indicator.y).toBe(230);
    });

    it('should handle a condition node without explicit dimensions', () => {
      // CHANGED (node model): condition node has no width -> default width used.
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 100 } },
        {
          id: 'cond-node',
          type: 'condition',
          position: { x: 250, y: 100 },
          data: { plugin: 'condition-1', conditionId: 'rt-1', __isConditionNode: true },
        },
        { id: 'node-2', position: { x: 400, y: 100 } },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      // Should use default dimensions and not throw
      expect(result.current.replayIndicators.length).toBe(1);
    });
  });

  describe('step type filtering', () => {
    it('should not create indicator for non-successor step types', () => {
      const replayData = [
        { type: 'execute', id: 'node-1', conditionId: 'condition-1' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    it('should not create indicator for step without conditionId', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('step changes', () => {
    it('should update indicators when currentReplayStep changes', () => {
      const replayData = [
        { type: 'execute', id: 'node-1' },
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result, rerender } = renderHook(
        ({ step }) =>
          useReplayIndicators({
            isReplayMode: true,
            currentReplayStep: step,
            replayData,
            edges: mockEdges,
            nodes: mockNodes,
          }),
        { initialProps: { step: 0 } }
      );

      // Step 0: no indicator (execute step)
      expect(result.current.replayIndicators).toEqual([]);

      // Step 1: has indicator (add successor step)
      rerender({ step: 1 });
      expect(result.current.replayIndicators.length).toBe(1);
    });

    it('should clear indicators when exiting replay mode', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result, rerender } = renderHook(
        ({ isReplayMode }) =>
          useReplayIndicators({
            isReplayMode,
            currentReplayStep: 0,
            replayData,
            edges: mockEdges,
            nodes: mockNodes,
          }),
        { initialProps: { isReplayMode: true } }
      );

      expect(result.current.replayIndicators.length).toBe(1);

      // Exit replay mode
      rerender({ isReplayMode: false });
      expect(result.current.replayIndicators).toEqual([]);
    });
  });
});
