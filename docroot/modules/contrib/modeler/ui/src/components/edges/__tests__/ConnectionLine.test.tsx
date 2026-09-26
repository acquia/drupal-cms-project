import React from 'react';
import { render } from '@testing-library/react';
import { Position, ConnectionLineType } from 'reactflow';
import ConnectionLine from '../ConnectionLine';
import { buildPreviewPath } from '../../../utils/edgePreviewPath';

/**
 * [C1] The custom new-edge connection line must render the SAME dashed
 * cubic-bezier preview as the endpoint-reconnect preview (issue #3585553
 * follow-on UX). These tests prove it builds its path with the shared
 * buildPreviewPath helper and carries the shared `.edge-reconnect-preview`
 * class — so dashed style / accent color / dark mode all match the reconnect
 * preview, and the shape is a cubic bezier (NOT a stepped SmoothStep path).
 */
describe('ConnectionLine (new-edge preview)', () => {
  const baseProps = {
    connectionLineType: ConnectionLineType.Bezier,
    fromX: 0,
    fromY: 0,
    toX: 100,
    toY: 200,
    fromPosition: Position.Bottom,
    toPosition: Position.Top,
    connectionStatus: null,
  };

  const renderLine = (props = {}) =>
    render(
      <svg>
        <ConnectionLine {...baseProps} {...props} />
      </svg>,
    );

  it('renders a single path carrying the shared dashed preview class', () => {
    renderLine();
    const path = document.querySelector('path.edge-reconnect-preview');
    expect(path).toBeInTheDocument();
  });

  it("builds its `d` with buildPreviewPath so it matches the reconnect preview", () => {
    renderLine();
    const path = document.querySelector('path.edge-reconnect-preview');
    const expected = buildPreviewPath(
      baseProps.fromX,
      baseProps.fromY,
      baseProps.fromPosition,
      baseProps.toX,
      baseProps.toY,
    );
    expect(path?.getAttribute('d')).toBe(expected);
  });

  it('renders a cubic bezier (M..C..), not a stepped path', () => {
    renderLine();
    const d = document.querySelector('path.edge-reconnect-preview')?.getAttribute('d') ?? '';
    expect(d.startsWith('M ')).toBe(true);
    expect(d).toContain('C'); // cubic bezier command
    // A stepped/cornered SmoothStep path uses L (line-to) segments — assert none.
    expect(d).not.toContain('L');
  });

  it('leaves the source handle along its Position (Bottom → curve drops down)', () => {
    renderLine({ fromPosition: Position.Bottom, fromX: 0, fromY: 0, toX: 0, toY: 100 });
    const d = document.querySelector('path.edge-reconnect-preview')?.getAttribute('d') ?? '';
    // distance = 100; controlDistance = min(25, 50) = 25; Bottom → first
    // control point is y + 25.
    expect(d).toContain('M 0,0 C 0,25');
  });

  it('has no fill so only the dashed stroke shows', () => {
    renderLine();
    const path = document.querySelector('path.edge-reconnect-preview');
    expect(path?.getAttribute('fill')).toBe('none');
  });
});
