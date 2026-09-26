import { Position } from 'reactflow';
import { buildPreviewPath } from '../edgePreviewPath';

describe('edgePreviewPath.buildPreviewPath', () => {
  it('starts at the fixed endpoint and ends at the cursor', () => {
    const d = buildPreviewPath(0, 0, Position.Bottom, 100, 200);
    expect(d.startsWith('M 0,0 ')).toBe(true);
    expect(d.trim().endsWith('100,200')).toBe(true);
    expect(d).toContain('C'); // cubic bezier
  });

  it('projects the fixed control point along the Bottom position', () => {
    // distance = 100; controlDistance = min(25, 50) = 25; Bottom → y + 25.
    const d = buildPreviewPath(0, 0, Position.Bottom, 0, 100);
    expect(d).toContain('M 0,0 C 0,25');
  });

  it('projects the fixed control point along the Right position', () => {
    // distance = 100; controlDistance = 25; Right → x + 25.
    const d = buildPreviewPath(0, 0, Position.Right, 100, 0);
    expect(d).toContain('M 0,0 C 25,0');
  });

  it('caps the control distance at 50 for long edges', () => {
    // distance = 1000; min(250, 50) = 50; Bottom → y + 50.
    const d = buildPreviewPath(0, 0, Position.Bottom, 0, 1000);
    expect(d).toContain('M 0,0 C 0,50');
  });

  it('produces a valid path even at zero distance', () => {
    const d = buildPreviewPath(50, 50, Position.Top, 50, 50);
    expect(d).toBe('M 50,50 C 50,50 50,50 50,50');
  });
});
