/**
 * Regression guard for issue #3589113 — the constraint that rules out simply
 * deleting useDebouncedField's initialValue sync.
 *
 * The hook's own comment says the sync exists "when selecting different node",
 * a job PropertyPanel already does better in its own effects keyed on
 * `node?.id` (PropertyPanel.tsx:518-534), which flush pending edits first. If
 * that were the whole story the effect could just be removed and the
 * value-keyed hazard would disappear with it.
 *
 * It is not the whole story. Undo reaches the property panel WITHOUT changing
 * the selection, over this path:
 *
 *   1. useHistory.undoAction() calls setNodes(previousState.nodes).
 *   2. History snapshots are rebuilt objects (useHistoryStore snapshotNode),
 *      so the restored node is never identical to the live selected node.
 *   3. useSelectionSync (Flow.tsx:537), keyed on the nodes array, finds the
 *      node by id, sees the identity differ, and calls setSelectedNode().
 *   4. Flow.tsx passes that object to PropertyPanel as `node`.
 *   5. PropertyPanel's identity effects key on `node?.id`, which has NOT
 *      changed, so they do not run.
 *   6. The only thing left that can update the input is useDebouncedField's
 *      effect on `initialValue`.
 *
 * So the sync is load-bearing, and the fix has to keep it while making it
 * refuse to overwrite local edits that are not saved yet. This file makes step 5
 * checkable rather than merely argued: the harness reproduces PropertyPanel's
 * identity effect exactly, so if the value-keyed sync is ever removed the
 * first test below fails and the input is left showing the newer label after
 * an undo.
 *
 * As in useConfigurationLabelRevert.test.tsx, the parent/child split is
 * load-bearing: useSelectionSync lives in the parent (Flow.tsx) while the
 * debounced field lives in the child (PropertyPanel), and React flushes child
 * effects before parent effects.
 */

import React, { useCallback, useEffect } from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { useGraphStore } from '../../store/useGraphStore';
import { useSelectionStore } from '../../store/useSelectionStore';
import { useHistoryStore } from '../../store/useHistoryStore';
import { useConfiguration } from '../useConfiguration';
import { useHistory } from '../useHistory';
import { useSelectionSync } from '../useSelectionSync';
import { useDebouncedField } from '../useDebouncedField';
import NodePropertiesPanel from '../../components/NodePropertiesPanel';
import { TIMING } from '../../constants/dimensions';
import type { StoreNode, NodeData } from '../../types/settings';

const OLD_LABEL = 'On Entity Insert';
const NEW_LABEL = 'On Entity Update';
const IN_FLIGHT_LABEL = 'On Entity Update Extra';

const eventNode: StoreNode = {
  id: 'event_1',
  type: 'start',
  position: { x: 0, y: 0 },
  data: {
    label: OLD_LABEL,
    plugin: 'example.entity_insert',
    componentType: 1,
    configuration: { field1: 'value1' },
  },
};

/** Captured from the harness so a test can trigger an undo or a redo. */
let undoAction: () => unknown = () => undefined;
let redoAction: () => unknown = () => undefined;

/** Every selected node id the panel has rendered with, in order. */
let renderedIds: string[] = [];

/** Mirrors PropertyPanel -> NodePropertiesPanel: owns the debounced fields. */
const PanelChild: React.FC<{
  onConfigurationChange: (nodeId: string, configuration: Record<string, unknown>) => void;
}> = ({ onConfigurationChange }) => {
  const selectedNode = useSelectionStore(state => state.selectedNode);

  const handleNodeLabelChange = useCallback((value: string) => {
    if (selectedNode) {
      onConfigurationChange(selectedNode.id, { _componentLabel: value });
    }
  }, [selectedNode, onConfigurationChange]);

  const nodeLabelField = useDebouncedField({
    initialValue: selectedNode?.data?.label || '',
    onDebouncedChange: handleNodeLabelChange,
  });

  const nodeAnnotationField = useDebouncedField({
    initialValue: selectedNode?.data?.annotation || '',
    onDebouncedChange: () => {},
  });

  // PropertyPanel.tsx:518-526, reproduced in shape. Present deliberately: it
  // is the effect that would have to cover undo if the value-keyed sync were
  // deleted, and the tests below show that it does not, because `id` is
  // unchanged across an undo.
  useEffect(() => {
    nodeLabelField.flush();
    nodeAnnotationField.flush();
    if (selectedNode) {
      nodeLabelField.setValue(selectedNode.data?.label || '');
      nodeAnnotationField.setValue(selectedNode.data?.annotation || '');
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedNode?.id]);

  // No dependency array: records the selection on every commit, so a test can
  // show the selection never changed while the value did.
  useEffect(() => {
    if (selectedNode) {
      renderedIds.push(selectedNode.id);
    }
  });

  if (!selectedNode) return null;

  return (
    <NodePropertiesPanel
      node={selectedNode}
      configurationForm={null}
      onConfigurationChange={
        onConfigurationChange as (nodeId: string, configuration: Record<string, NodeData>) => void
      }
      isLocked={false}
      nodeLabelField={nodeLabelField}
      nodeAnnotationField={nodeAnnotationField}
    />
  );
};

/** Mirrors Flow.tsx: owns history, configuration and useSelectionSync (Flow.tsx:537). */
const UndoHarness: React.FC = () => {
  const { saveHistory, undo, redo } = useHistory({ setHasUnsavedChanges: () => {} });
  const { onConfigurationChange } = useConfiguration({
    setHasUnsavedChanges: () => {},
    saveHistory,
  });
  undoAction = undo;
  redoAction = redo;
  useSelectionSync();
  return <PanelChild onConfigurationChange={onConfigurationChange} />;
};

/** Renders, selects the single node and returns the label input. */
const renderAndSelect = (): HTMLInputElement => {
  render(<UndoHarness />);
  act(() => {
    useSelectionStore.getState().selectNode(useGraphStore.getState().nodes[0]);
  });
  return screen.getByRole('textbox', { name: 'Label' }) as HTMLInputElement;
};

describe('undo reaches the label field through the initialValue sync (issue #3589113)', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    renderedIds = [];
    undoAction = () => undefined;
    redoAction = () => undefined;
    useGraphStore.setState({ nodes: [{ ...eventNode }], edges: [] });
    useSelectionStore.getState().clearSelection();
    useHistoryStore.getState().clearHistory();
  });

  afterEach(() => {
    act(() => {
      jest.runOnlyPendingTimers();
    });
    jest.useRealTimers();
    useSelectionStore.getState().clearSelection();
    useGraphStore.setState({ nodes: [], edges: [] });
    useHistoryStore.getState().clearHistory();
  });

  it('restores the previous label in the input when the selection has not changed', () => {
    const input = renderAndSelect();
    expect(input.value).toBe(OLD_LABEL);

    // The user renames the node and pauses long enough for the debounce to
    // commit. onConfigurationChange pushes a history entry before writing.
    act(() => {
      fireEvent.change(input, { target: { value: NEW_LABEL } });
    });
    act(() => {
      jest.advanceTimersByTime(TIMING.DEBOUNCE_DELAY);
    });
    expect(useGraphStore.getState().nodes[0].data?.label).toBe(NEW_LABEL);
    expect(input.value).toBe(NEW_LABEL);

    const idsBeforeUndo = renderedIds.length;

    // Undo, with nothing typed since - so no debounce is pending.
    act(() => {
      undoAction();
    });

    // The store went back...
    expect(useGraphStore.getState().nodes[0].data?.label).toBe(OLD_LABEL);
    // ...and so did the input. Nothing but the value-keyed sync can have done
    // this: the identity effect is in the harness and did not run, because...
    expect(input.value).toBe(OLD_LABEL);
    // ...the selected node id never changed across the undo.
    expect(new Set(renderedIds.slice(idsBeforeUndo))).toEqual(new Set([eventNode.id]));
    expect(new Set(renderedIds)).toEqual(new Set([eventNode.id]));

    // Redo takes the same route back (useHistory.redoAction mirrors undoAction),
    // so the input must follow it too.
    act(() => {
      redoAction();
    });
    expect(useGraphStore.getState().nodes[0].data?.label).toBe(NEW_LABEL);
    expect(input.value).toBe(NEW_LABEL);
    expect(new Set(renderedIds)).toEqual(new Set([eventNode.id]));
  });

  it('keeps typing that is not saved yet when undo arrives mid-edit', () => {
    // The deliberate trade-off of suppressing the sync while a debounce is
    // pending: an undo that lands mid-keystroke loses to the user's own
    // unsaved text, which then commits normally. This is the same outcome
    // the user would get by typing immediately after the undo, and it is the
    // price of never discarding in-flight input.
    const input = renderAndSelect();

    act(() => {
      fireEvent.change(input, { target: { value: NEW_LABEL } });
    });
    act(() => {
      jest.advanceTimersByTime(TIMING.DEBOUNCE_DELAY);
    });
    expect(useGraphStore.getState().nodes[0].data?.label).toBe(NEW_LABEL);

    // The user carries on typing; this edit is still settling.
    act(() => {
      fireEvent.change(input, { target: { value: IN_FLIGHT_LABEL } });
    });

    act(() => {
      undoAction();
    });

    // The typed text survived the undo.
    expect(input.value).toBe(IN_FLIGHT_LABEL);

    // And it commits when its debounce settles.
    act(() => {
      jest.advanceTimersByTime(TIMING.DEBOUNCE_DELAY);
    });
    expect(useGraphStore.getState().nodes[0].data?.label).toBe(IN_FLIGHT_LABEL);
    expect(input.value).toBe(IN_FLIGHT_LABEL);
  });
});
