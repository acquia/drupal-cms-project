/**
 * Drift lock: the replay step list and the canvas MUST render the same icon
 * for the same node type.
 *
 * These tests render the REAL canvas node components and the REAL
 * `getStepIcon()` output, then compare the resulting SVG markup. If anyone
 * swaps an icon in one place but not the other, these fail.
 */

import React from 'react';
import { render } from '@testing-library/react';
import { FiZap, FiActivity, FiGitBranch, FiFilter, FiLayers } from 'react-icons/fi';
import { NODE_TYPE_ICONS, DEFAULT_NODE_ICON, getNodeTypeIcon } from '../nodeIcons';
import { getStepIcon, ReplayStep } from '../replayStepUtils';
import type { StoreNode as Node } from '../../types/settings';
import StartNode from '../../components/nodes/StartNode';
import CustomNode from '../../components/nodes/CustomNode';
import GatewayNode from '../../components/nodes/GatewayNode';
import ConditionNode from '../../components/nodes/ConditionNode';
import SubprocessNode from '../../components/nodes/SubprocessNode';

// Mock ReactFlow so the node components render standalone in jsdom.
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id }: { type: string; position: string; id?: string }) => (
    <div data-testid={`handle-${type}-${id}`} data-position={position} />
  ),
  Position: { Top: 'top', Bottom: 'bottom', Left: 'left', Right: 'right' },
}));

/** Extract the inner SVG markup (path data) of the first matching icon. */
const iconMarkup = (element: React.ReactElement, selector: string): string => {
  const { container } = render(element);
  const svg = container.querySelector(selector);
  if (!svg) throw new Error(`No icon matched selector "${selector}"`);
  return svg.innerHTML;
};

/** The glyph the CANVAS renders for a node component. */
const canvasIcon = (element: React.ReactElement): string => iconMarkup(element, 'svg.node-icon');

/** The glyph the STEP LIST renders for a replay step. */
const stepIcon = (step: ReplayStep, nodes: Node[] = []): string =>
  iconMarkup(<>{getStepIcon(step, nodes)}</>, 'svg.step-icon');

/** The glyph a bare react-icons component renders. */
const referenceIcon = (Icon: React.ComponentType): string => {
  const { container } = render(<Icon />);
  return container.querySelector('svg')!.innerHTML;
};

const baseNodeProps = {
  selected: false,
  xPos: 0,
  yPos: 0,
  isConnectable: true,
  zIndex: 0,
  dragging: false,
  targetPosition: undefined,
  sourcePosition: undefined,
};

describe('nodeIcons', () => {
  describe('NODE_TYPE_ICONS map', () => {
    it('maps every canvas node type to its documented icon', () => {
      expect(NODE_TYPE_ICONS.start).toBe(FiZap);
      expect(NODE_TYPE_ICONS.element).toBe(FiActivity);
      expect(NODE_TYPE_ICONS.gateway).toBe(FiGitBranch);
      expect(NODE_TYPE_ICONS.condition).toBe(FiFilter);
      expect(NODE_TYPE_ICONS.subprocess).toBe(FiLayers);
    });

    it('defaults to the action icon', () => {
      expect(DEFAULT_NODE_ICON).toBe(FiActivity);
    });
  });

  describe('getNodeTypeIcon', () => {
    it('resolves canonical type names', () => {
      expect(getNodeTypeIcon('start')).toBe(FiZap);
      expect(getNodeTypeIcon('gateway')).toBe(FiGitBranch);
      expect(getNodeTypeIcon('condition')).toBe(FiFilter);
      expect(getNodeTypeIcon('subprocess')).toBe(FiLayers);
      expect(getNodeTypeIcon('element')).toBe(FiActivity);
    });

    it('resolves aliases used by model data', () => {
      expect(getNodeTypeIcon('event')).toBe(FiZap);
      expect(getNodeTypeIcon('action')).toBe(FiActivity);
      expect(getNodeTypeIcon('link')).toBe(FiFilter);
    });

    it('falls back to the default icon for unknown/missing types', () => {
      expect(getNodeTypeIcon('nope')).toBe(DEFAULT_NODE_ICON);
      expect(getNodeTypeIcon(undefined)).toBe(DEFAULT_NODE_ICON);
      expect(getNodeTypeIcon(null)).toBe(DEFAULT_NODE_ICON);
      expect(getNodeTypeIcon('')).toBe(DEFAULT_NODE_ICON);
    });
  });

  // ---- The actual drift lock ------------------------------------------------

  describe('canvas and step list render identical icons', () => {
    it('event node: StartNode === "started" step icon', () => {
      const canvas = canvasIcon(
        <StartNode {...baseNodeProps} id="e1" type="start" data={{ label: 'Event' }} />
      );
      const step = stepIcon({ type: 'started', id: 'e1' });

      expect(step).toBe(canvas);
      expect(step).toBe(referenceIcon(FiZap));
    });

    it('action node: CustomNode === "execute" step icon', () => {
      const nodes: Node[] = [
        { id: 'a1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Act' } },
      ];
      const canvas = canvasIcon(
        <CustomNode {...baseNodeProps} id="a1" type="element" data={{ label: 'Act' }} />
      );
      const step = stepIcon({ type: 'execute', id: 'a1' }, nodes);

      expect(step).toBe(canvas);
      expect(step).toBe(referenceIcon(FiActivity));
    });

    it('subprocess node: SubprocessNode === "execute" step icon', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'subprocess', position: { x: 0, y: 0 }, data: { label: 'Sub' } },
      ];
      const canvas = canvasIcon(
        <SubprocessNode {...baseNodeProps} id="s1" type="subprocess" data={{ label: 'Sub' }} />
      );
      const step = stepIcon({ type: 'execute', id: 's1' }, nodes);

      expect(step).toBe(canvas);
      expect(step).toBe(referenceIcon(FiLayers));
    });

    it('gateway node: GatewayNode === gateway successor step icon', () => {
      const nodes: Node[] = [
        { id: 'n1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'N1' } },
        { id: 'g1', type: 'gateway', position: { x: 0, y: 0 }, data: { label: 'GW' } },
      ];
      const canvas = canvasIcon(
        <GatewayNode {...baseNodeProps} id="g1" type="gateway" data={{ label: 'GW' }} />
      );
      const step = stepIcon({ type: 'add successor', id: 'n1', successorId: 'g1' }, nodes);

      expect(step).toBe(canvas);
      expect(step).toBe(referenceIcon(FiGitBranch));
    });

    it('condition node: ConditionNode === condition step icon', () => {
      const nodes: Node[] = [
        { id: 'n1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'N1' } },
        {
          id: 'c1',
          type: 'condition',
          position: { x: 0, y: 0 },
          data: { label: 'Field is empty', conditionId: 'cfg-1', __isConditionNode: true },
        },
      ];
      const canvas = canvasIcon(
        <ConditionNode {...baseNodeProps} id="c1" type="condition" data={{ label: 'Field is empty' }} />
      );
      const step = stepIcon(
        { type: 'add successor', id: 'n1', successorId: 'n2', conditionId: 'cfg-1' },
        nodes
      );

      expect(step).toBe(canvas);
      expect(step).toBe(referenceIcon(FiFilter));
    });
  });
});
