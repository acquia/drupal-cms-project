/**
 * Regression test for issue #3589111 — the user-visible symptom.
 *
 * onConfigurationChange used to re-sync the selection store behind a 10ms
 * setTimeout, reading a render-time `nodes` snapshot that setNodes() had not
 * updated. useConfiguration.test.ts guards the structure (the callback must
 * not write to the selection store). This file guards what the user actually
 * lost: characters typed into the label field were discarded.
 *
 * The mechanism is worth stating precisely, because it is not the obvious one.
 * The stale write does NOT corrupt the field by showing the old label — the
 * field's local value is untouched by it, and useSelectionSync restores the
 * fresh node almost immediately. The damage comes from that restoration: the
 * stale write flips the selected node's label from the just-saved value to the
 * previous one, and the heal flips it back. useDebouncedField syncs on the
 * STRING value of `initialValue` (useDebouncedField.ts:55-58), so that
 * round trip is a spurious dependency change, and the resulting
 * setValue(committedLabel) overwrites whatever the user has typed since.
 * Without the stale write `initialValue` never changes, the sync effect never
 * fires, and the keystrokes survive.
 *
 * Observed timeline with the defect present (label debounce is 300ms):
 *   t+0     user's edit commits, user keeps typing " Extra"
 *   t+10ms  stale write, then heal -> setValue() wipes " Extra"
 *   t+300ms the pending debounce re-applies " Extra"
 * so the typed text vanished from the input for ~290ms. That window is far
 * too long to be a paint-level flicker and far too short for a Playwright
 * auto-retrying assertion to catch reliably, which is why this is a
 * fake-timer test at the component level rather than an E2E test.
 *
 * The loop under test spans four units, so this uses the REAL stores, the
 * REAL useSelectionSync, the REAL useDebouncedField and the REAL
 * NodePropertiesPanel (which owns the `#modeler-component-label` input).
 * Only Flow.tsx's wiring is reproduced, and the parent/child split is
 * deliberate and load-bearing: useSelectionSync lives in Flow.tsx (parent)
 * while the debounced field lives in PropertyPanel (child), and React flushes
 * child effects before parent effects. Collapsing both into one component
 * inverts that order and hides the defect.
 */

import React, { useCallback, useEffect } from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { useGraphStore } from '../../store/useGraphStore';
import { useSelectionStore } from '../../store/useSelectionStore';
import { useConfiguration } from '../useConfiguration';
import { useSelectionSync } from '../useSelectionSync';
import { useDebouncedField } from '../useDebouncedField';
import NodePropertiesPanel from '../../components/NodePropertiesPanel';
import { TIMING } from '../../constants/dimensions';
import type { StoreNode, NodeData } from '../../types/settings';

const OLD_LABEL = 'On Entity Insert';
const SAVED_LABEL = 'On Entity Update';
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

/** The label input's value at the end of every commit, in order. */
let committedValues: string[] = [];

/** Mirrors PropertyPanel -> NodePropertiesPanel: owns the debounced label field. */
const PanelChild: React.FC<{
  onConfigurationChange: (nodeId: string, configuration: Record<string, unknown>) => void;
}> = ({ onConfigurationChange }) => {
  const selectedNode = useSelectionStore(state => state.selectedNode);

  // PropertyPanel.tsx:477-487 — the label's own debounce feeds the handler
  // that used to corrupt it. That is what makes the label the reproducing path.
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

  // No dependency array: runs after EVERY commit, recording what the input
  // actually showed at that point.
  useEffect(() => {
    const input = document.getElementById('modeler-component-label');
    if (input instanceof HTMLInputElement) {
      committedValues.push(input.value);
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

/** Mirrors Flow.tsx: owns useConfiguration and calls useSelectionSync (Flow.tsx:537). */
const LabelHarness: React.FC = () => {
  const { onConfigurationChange } = useConfiguration({ setHasUnsavedChanges: () => {} });
  useSelectionSync();
  return <PanelChild onConfigurationChange={onConfigurationChange} />;
};

describe('label editing survives a configuration change (issue #3589111)', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    committedValues = [];
    useGraphStore.setState({ nodes: [{ ...eventNode }], edges: [] });
    useSelectionStore.getState().clearSelection();
  });

  afterEach(() => {
    act(() => {
      jest.runOnlyPendingTimers();
    });
    jest.useRealTimers();
    useSelectionStore.getState().clearSelection();
    useGraphStore.setState({ nodes: [], edges: [] });
  });

  it('keeps characters typed while the previous edit is still settling', () => {
    render(<LabelHarness />);

    act(() => {
      useSelectionStore.getState().selectNode(useGraphStore.getState().nodes[0]);
    });

    const input = screen.getByRole('textbox', { name: 'Label' }) as HTMLInputElement;
    expect(input.value).toBe(OLD_LABEL);

    // The user renames the node and pauses long enough for the debounce to
    // commit the edit through onConfigurationChange.
    act(() => {
      fireEvent.change(input, { target: { value: SAVED_LABEL } });
    });
    act(() => {
      jest.advanceTimersByTime(TIMING.DEBOUNCE_DELAY);
    });
    expect(useGraphStore.getState().nodes[0].data?.label).toBe(SAVED_LABEL);

    // The user carries on typing. These keystrokes have not been committed
    // yet — their own debounce is still pending.
    act(() => {
      fireEvent.change(input, { target: { value: IN_FLIGHT_LABEL } });
    });
    expect(input.value).toBe(IN_FLIGHT_LABEL);

    // Step through the window the removed setTimeout used to fire in, one
    // millisecond at a time, so a wipe cannot hide inside a single flush.
    // Stop well short of the pending debounce, which would mask the loss by
    // re-applying the typed text.
    const firstCommitAfterTyping = committedValues.length;
    for (let ms = 0; ms < 50; ms++) {
      act(() => {
        jest.advanceTimersByTime(1);
      });
    }

    // Any committed value other than the in-flight text is a keystroke wipe.
    // Asserting on the offending values (rather than a boolean) makes a
    // future failure show exactly what the field was reset to.
    const wipedTo = committedValues
      .slice(firstCommitAfterTyping)
      .filter(value => value !== IN_FLIGHT_LABEL);
    expect(wipedTo).toEqual([]);
    expect(input.value).toBe(IN_FLIGHT_LABEL);
  });
});
