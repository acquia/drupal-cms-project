/**
 * Tests for replayExpansion.
 *
 * Verifies the frontend lazy expansion of ECA's compact `@prev`/`@ref`/`@same`
 * dedup markers, mirroring ProcessDebugger::expandHistory(),
 * ProcessDebugger::expandRefs(), and ProcessDebugger::expandSame().  Critically,
 * expansion must never mutate its inputs, so the compact form survives for the
 * JSON export.
 */

import type { ReplayDataEntry } from '../../types/settings';
import {
  TOKEN_DATA_PREV,
  TOKEN_DATA_REF,
  TOKEN_DATA_SAME,
  expandRefs,
  expandReplayStep,
} from '../replayExpansion';

describe('replayExpansion', () => {
  describe('expandRefs', () => {
    it('resolves a @ref entry to the sibling key data', () => {
      const stepData = {
        node: { label: 'Node', token: '[node]', data: { title: 'Hello' } },
        entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
      };

      const result = expandRefs(stepData);

      expect(result.entity.data).toEqual({ title: 'Hello' });
      expect(result.entity[TOKEN_DATA_REF]).toBeUndefined();
      // The referenced entry is untouched.
      expect(result.node.data).toEqual({ title: 'Hello' });
    });

    it('leaves entries without a @ref marker unchanged', () => {
      const stepData = {
        node: { label: 'Node', token: '[node]', data: { title: 'Hello' } },
      };

      const result = expandRefs(stepData);

      expect(result.node).toEqual({ label: 'Node', token: '[node]', data: { title: 'Hello' } });
    });

    it('leaves a @ref entry untouched when the referenced key is missing', () => {
      const stepData = {
        entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'missing' },
      };

      const result = expandRefs(stepData);

      // Mirrors the PHP guard: no target -> marker stays, no data added.
      expect(result.entity[TOKEN_DATA_REF]).toBe('missing');
      expect('data' in result.entity).toBe(false);
    });

    it('leaves a @ref entry untouched when the referenced key has no data', () => {
      const stepData = {
        node: { label: 'Node', token: '[node]' },
        entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
      };

      const result = expandRefs(stepData);

      expect(result.entity[TOKEN_DATA_REF]).toBe('node');
      expect('data' in result.entity).toBe(false);
    });

    it('does NOT mutate the input object', () => {
      const stepData = {
        node: { label: 'Node', token: '[node]', data: { title: 'Hello' } },
        entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
      };
      const snapshot = JSON.parse(JSON.stringify(stepData));

      expandRefs(stepData);

      expect(stepData).toEqual(snapshot);
      expect(stepData.entity[TOKEN_DATA_REF]).toBe('node');
      expect('data' in stepData.entity).toBe(false);
    });
  });

  describe('expandReplayStep', () => {
    it('returns null for an out-of-range index', () => {
      const steps: ReplayDataEntry[] = [{ type: 'started', data: {} }];
      expect(expandReplayStep(steps, -1)).toBeNull();
      expect(expandReplayStep(steps, 1)).toBeNull();
    });

    it('expands a plain step with no markers', () => {
      const steps: ReplayDataEntry[] = [
        { type: 'started', data: { node: { label: 'Node', data: { title: 'A' } } } },
      ];

      expect(expandReplayStep(steps, 0)).toEqual({
        node: { label: 'Node', data: { title: 'A' } },
      });
    });

    it('expands @ref markers within a step', () => {
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
      ];

      const result = expandReplayStep(steps, 0);

      expect(result).toEqual({
        node: { label: 'Node', token: '[node]', data: { title: 'A' } },
        entity: { label: 'Entity', token: '[entity]', data: { title: 'A' } },
      });
    });

    it('resolves @prev to the previous step expanded data', () => {
      const steps: ReplayDataEntry[] = [
        { type: 'started', data: { node: { label: 'Node', data: { title: 'A' } } } },
        { type: 'execute', data: TOKEN_DATA_PREV },
      ];

      const first = expandReplayStep(steps, 0);
      const second = expandReplayStep(steps, 1);

      expect(second).toEqual(first);
      expect(second).toEqual({ node: { label: 'Node', data: { title: 'A' } } });
    });

    it('resolves chained @prev markers across multiple steps', () => {
      const steps: ReplayDataEntry[] = [
        { type: 'started', data: { node: { label: 'Node', data: { title: 'A' } } } },
        { type: 'execute', data: TOKEN_DATA_PREV },
        { type: 'execute', data: TOKEN_DATA_PREV },
      ];

      expect(expandReplayStep(steps, 2)).toEqual({
        node: { label: 'Node', data: { title: 'A' } },
      });
    });

    it('resolves @prev that reuses already @ref-expanded data', () => {
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
        { type: 'execute', data: TOKEN_DATA_PREV },
      ];

      const result = expandReplayStep(steps, 1);

      // The reused @prev data carries the already-resolved @ref data.
      expect(result).toEqual({
        node: { label: 'Node', token: '[node]', data: { title: 'A' } },
        entity: { label: 'Entity', token: '[entity]', data: { title: 'A' } },
      });
      expect(result?.entity[TOKEN_DATA_REF]).toBeUndefined();
    });

    it('treats a leading @prev marker as empty token data', () => {
      const steps: ReplayDataEntry[] = [
        { type: 'started', data: TOKEN_DATA_PREV },
      ];

      expect(expandReplayStep(steps, 0)).toEqual({});
    });

    it('treats missing or scalar step data as empty token data', () => {
      const steps: ReplayDataEntry[] = [
        { type: 'started' },
        { type: 'does not apply', data: 'unexpected-scalar' as unknown as Record<string, never> },
      ];

      expect(expandReplayStep(steps, 0)).toEqual({});
      expect(expandReplayStep(steps, 1)).toEqual({});
    });

    it('does NOT mutate the input steps (markers survive for export)', () => {
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
        { type: 'execute', data: TOKEN_DATA_PREV },
      ];
      const snapshot = JSON.parse(JSON.stringify(steps));

      // View both steps.
      expandReplayStep(steps, 0);
      expandReplayStep(steps, 1);

      // The compact markers must still be present in the original array.
      expect(steps).toEqual(snapshot);
      expect(steps[1].data).toBe(TOKEN_DATA_PREV);
      const firstData = steps[0].data as Record<string, Record<string, unknown>>;
      expect(firstData.entity[TOKEN_DATA_REF]).toBe('node');
      expect('data' in firstData.entity).toBe(false);
    });
  });

  describe('expandReplayStep - @same', () => {
    it('resolves a top-level @same marker to the first occurrence data', () => {
      // steps[0].data.node.data is the first occurrence; step 1's node points
      // at it by (step: 0, path: 'node').
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
          },
        },
        {
          type: 'execute',
          data: {
            node: {
              label: 'Node',
              token: '[node]',
              [TOKEN_DATA_SAME]: { step: 0, path: 'node' },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 1);

      expect(result).toEqual({
        node: { label: 'Node', token: '[node]', data: { title: 'A' } },
      });
      expect(result?.node[TOKEN_DATA_SAME]).toBeUndefined();
    });

    it('resolves a nested @same path by walking the interleaved data segments', () => {
      // The nested example from the ECA TOKEN_DATA_SAME contract:
      // 'entity/data/user_picture/data/entity' resolves to
      // steps[0].data.entity.data.user_picture.data.entity.
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            entity: {
              label: 'Entity',
              token: '[entity]',
              data: {
                user_picture: {
                  label: 'Picture',
                  token: '[entity:user_picture]',
                  data: {
                    entity: {
                      label: 'File',
                      token: '[entity:user_picture:entity]',
                      data: { filename: 'avatar.png' },
                    },
                  },
                },
              },
            },
          },
        },
        {
          type: 'execute',
          data: {
            author: {
              label: 'Author',
              token: '[author]',
              [TOKEN_DATA_SAME]: {
                step: 0,
                path: 'entity/data/user_picture/data/entity',
              },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 1);

      // The resolved NODE's `data` is what gets spliced in.
      expect(result?.author.data).toEqual({ filename: 'avatar.png' });
      expect(result?.author[TOKEN_DATA_SAME]).toBeUndefined();
      // label/token are retained on the marker entry.
      expect(result?.author.label).toBe('Author');
      expect(result?.author.token).toBe('[author]');
    });

    it('resolves a @same marker nested inside the displayed step tree', () => {
      // The marker can appear at a nested level of the displayed step, not just
      // at the top level. expandSameNode recurses into children.
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
          },
        },
        {
          type: 'execute',
          data: {
            wrapper: {
              label: 'Wrapper',
              token: '[wrapper]',
              data: {
                inner: {
                  label: 'Inner',
                  token: '[wrapper:inner]',
                  [TOKEN_DATA_SAME]: { step: 0, path: 'node' },
                },
              },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 1);

      const wrapperData = result?.wrapper.data as Record<string, Record<string, unknown>>;
      expect(wrapperData.inner.data).toEqual({ title: 'A' });
      expect(wrapperData.inner[TOKEN_DATA_SAME]).toBeUndefined();
    });

    it('resolves @same against a step that itself used @ref (phase order)', () => {
      // Step 0 carries a @ref that, once expanded, gives `entity` a `data`
      // sub-tree. Step 1's @same points at steps[0].data.entity — which only
      // has data AFTER the @ref pass. This proves @same runs after @ref.
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
        {
          type: 'execute',
          data: {
            copy: {
              label: 'Copy',
              token: '[copy]',
              [TOKEN_DATA_SAME]: { step: 0, path: 'entity' },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 1);

      // entity's @ref-resolved data ({ title: 'A' }) is what @same reads.
      expect(result?.copy.data).toEqual({ title: 'A' });
      expect(result?.copy[TOKEN_DATA_SAME]).toBeUndefined();
    });

    it('resolves @same against a step that itself used @prev (phase order)', () => {
      // Step 1 is @prev (reuses step 0's expanded data). Step 2's @same points
      // at (step: 1, path: 'node'); resolving it requires step 1 to already
      // carry its @prev-expanded data.
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
          },
        },
        { type: 'execute', data: TOKEN_DATA_PREV },
        {
          type: 'execute',
          data: {
            copy: {
              label: 'Copy',
              token: '[copy]',
              [TOKEN_DATA_SAME]: { step: 1, path: 'node' },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 2);

      expect(result?.copy.data).toEqual({ title: 'A' });
      expect(result?.copy[TOKEN_DATA_SAME]).toBeUndefined();
    });

    it('leaves the entry without data and removes the marker for an unresolvable path', () => {
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
          },
        },
        {
          type: 'execute',
          data: {
            broken: {
              label: 'Broken',
              token: '[broken]',
              [TOKEN_DATA_SAME]: { step: 0, path: 'missing' },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 1);

      // Defensive: marker removed, no `data` added, no throw.
      expect(result?.broken[TOKEN_DATA_SAME]).toBeUndefined();
      expect('data' in (result?.broken ?? {})).toBe(false);
      expect(result?.broken.label).toBe('Broken');
    });

    it('leaves the entry without data when the referenced step is out of range', () => {
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            broken: {
              label: 'Broken',
              token: '[broken]',
              [TOKEN_DATA_SAME]: { step: 5, path: 'node' },
            },
          },
        },
      ];

      const result = expandReplayStep(steps, 0);

      expect(result?.broken[TOKEN_DATA_SAME]).toBeUndefined();
      expect('data' in (result?.broken ?? {})).toBe(false);
    });

    it('resolves an intra-step @ref whose sibling is itself a @same marker', () => {
      // The exact failing shape: within step 1, `user` is a @ref to sibling
      // `entity`, but `entity` is itself a cross-step @same marker (unchanged
      // since step 0). Resolution order must be @prev -> @same -> @ref so that,
      // when @ref runs, `entity` already carries its real `data`. A differing
      // token (`extra`) keeps step 1 from collapsing to @prev.
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            entity: { label: 'Entity', token: 'entity', data: { title: 'A' } },
            user: { label: 'User', token: 'user', [TOKEN_DATA_REF]: 'entity' },
          },
        },
        {
          type: 'execute',
          data: {
            entity: {
              label: 'Entity',
              token: 'entity',
              [TOKEN_DATA_SAME]: { step: 0, path: 'entity' },
            },
            user: { label: 'User', token: 'user', [TOKEN_DATA_REF]: 'entity' },
            extra: { label: 'Extra', token: 'extra', data: { title: 'B' } },
          },
        },
      ];

      const result = expandReplayStep(steps, 1);

      // Both entity and user resolve to step 0's entity data; markers removed.
      expect(result?.entity.data).toEqual({ title: 'A' });
      expect(result?.entity[TOKEN_DATA_SAME]).toBeUndefined();
      expect(result?.user.data).toEqual({ title: 'A' });
      expect(result?.user[TOKEN_DATA_REF]).toBeUndefined();
      expect(result?.extra.data).toEqual({ title: 'B' });
    });

    it('expands all three markers coexisting in one history', () => {
      // Step 0: a @ref. Step 1: @prev. Step 2: a top-level @same plus a fresh
      // node, exercising @prev/@ref/@same together.
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
        { type: 'execute', data: TOKEN_DATA_PREV },
        {
          type: 'execute',
          data: {
            fresh: { label: 'Fresh', token: '[fresh]', data: { title: 'B' } },
            same: {
              label: 'Same',
              token: '[same]',
              [TOKEN_DATA_SAME]: { step: 0, path: 'node' },
            },
          },
        },
      ];

      // Step 0: @ref resolved.
      expect(expandReplayStep(steps, 0)).toEqual({
        node: { label: 'Node', token: '[node]', data: { title: 'A' } },
        entity: { label: 'Entity', token: '[entity]', data: { title: 'A' } },
      });

      // Step 1: @prev reuses step 0's expanded data.
      expect(expandReplayStep(steps, 1)).toEqual({
        node: { label: 'Node', token: '[node]', data: { title: 'A' } },
        entity: { label: 'Entity', token: '[entity]', data: { title: 'A' } },
      });

      // Step 2: fresh node kept, @same resolved against step 0.
      expect(expandReplayStep(steps, 2)).toEqual({
        fresh: { label: 'Fresh', token: '[fresh]', data: { title: 'B' } },
        same: { label: 'Same', token: '[same]', data: { title: 'A' } },
      });
    });

    it('does NOT mutate the input steps when @same markers are expanded', () => {
      const steps: ReplayDataEntry[] = [
        {
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
        { type: 'execute', data: TOKEN_DATA_PREV },
        {
          type: 'execute',
          data: {
            same: {
              label: 'Same',
              token: '[same]',
              [TOKEN_DATA_SAME]: { step: 0, path: 'node' },
            },
          },
        },
      ];
      const snapshot = JSON.parse(JSON.stringify(steps));

      // View every step, exercising the full expansion path.
      expandReplayStep(steps, 0);
      expandReplayStep(steps, 1);
      expandReplayStep(steps, 2);

      expect(steps).toEqual(snapshot);
      // The compact @same marker survives for export.
      const thirdData = steps[2].data as Record<string, Record<string, unknown>>;
      expect(thirdData.same[TOKEN_DATA_SAME]).toEqual({ step: 0, path: 'node' });
      expect('data' in thirdData.same).toBe(false);
    });
  });
});
