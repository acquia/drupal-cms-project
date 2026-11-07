/**
 * Regression guard: the replay step list must be scrollable in BOTH contexts.
 *
 * `ReplayPanelContent` renders in two places:
 *   1. inside the standalone `.replay-panel` wrapper (ReplayPanel.tsx), and
 *   2. embedded in PropertyPanel's "Review flow" mode, which has NO
 *      `.replay-panel` ancestor.
 *
 * Scrolling used to be declared only on the `.replay-panel .replay-steps`
 * override, so in context (2) the list had no `overflow-y` and simply clipped.
 *
 * WHAT THESE TESTS PROVE: that a base `.replay-steps` rule carrying
 * `overflow-y: auto` + `min-height: 0` exists in the stylesheet, and that the
 * embedded DOM really does render `.replay-steps` WITHOUT a `.replay-panel`
 * ancestor (so the base rule is the only thing that can make it scroll).
 *
 * WHAT THEY DO NOT PROVE: that the list actually scrolls. jsdom performs no
 * layout, so no unit test can assert real overflow. The visual behavior must
 * be checked manually in a browser.
 */

import React from 'react';
import { render } from '@testing-library/react';
import fs from 'fs';
import path from 'path';
import ReplayPanelContent from '../ReplayPanelContent';
import type { ReplayStep } from '../../utils/replayStepUtils';
import type { ReplayEntry } from '../../hooks/useReplayLoader';
import type { StoreNode as Node } from '../../types/settings';

/** Strip comments and split the stylesheet into { selector, body } rules. */
function parseRules(css: string): { selector: string; body: string }[] {
  return css
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .split('}')
    .map(chunk => {
      const parts = chunk.split('{');
      if (parts.length < 2) return null;
      return { selector: parts[0].trim(), body: parts[1].trim() };
    })
    .filter((r): r is { selector: string; body: string } => r !== null);
}

describe('replay step list scrolling', () => {
  const cssPath = path.resolve(__dirname, '../../styles/modeler.css');
  const css = fs.readFileSync(cssPath, 'utf8');
  const rules = parseRules(css);

  const selectorsFor = (name: string) =>
    rules.filter(r => r.selector.split(',').map(s => s.trim()).includes(name));

  describe('stylesheet contract', () => {
    it('declares a BASE .replay-steps rule (not scoped to .replay-panel)', () => {
      expect(selectorsFor('.replay-steps').length).toBeGreaterThan(0);
    });

    it('the base rule makes the step list the scroll container', () => {
      const base = selectorsFor('.replay-steps')[0];
      expect(base.body).toMatch(/overflow-y:\s*auto/);
      // Without min-height:0 a flex child refuses to shrink below its content,
      // so overflow-y would never engage inside the review body.
      expect(base.body).toMatch(/min-height:\s*0/);
    });

    it('keeps the standalone .replay-panel override intact', () => {
      const scoped = rules.find(r => r.selector === '.replay-panel .replay-steps');
      expect(scoped).toBeDefined();
      expect(scoped!.body).toMatch(/max-height:\s*300px/);
      expect(scoped!.body).toMatch(/overflow-y:\s*auto/);
    });

    it('lets the review-body override fill remaining height without re-declaring overflow', () => {
      const body = rules.find(r => r.selector === '.replay-control-section .replay-steps');
      expect(body).toBeDefined();
      expect(body!.body).toMatch(/max-height:\s*none/);
      expect(body!.body).toMatch(/flex:\s*1/);
      // It must NOT reset overflow — it inherits `auto` from the base rule.
      expect(body!.body).not.toMatch(/overflow-y:\s*(hidden|visible|clip)/);
    });

    it('keeps the step list as the scroll container even though inline step data scrolls too', () => {
      // The inline step-data block under the selected row is its own scroll
      // container, but it must not take scrolling away from the list, which is
      // what the playback auto-scroll drives.
      const inline = rules.find(r => r.selector === '.replay-step-data');
      expect(inline).toBeDefined();
      expect(inline!.body).toMatch(/overflow-y:\s*auto/);
      expect(inline!.body).toMatch(/max-height:/);
      const base = selectorsFor('.replay-steps')[0];
      expect(base.body).toMatch(/overflow-y:\s*auto/);
    });

    it('uses only --modeler-* custom properties in the base rule (no hardcoded colors)', () => {
      const base = selectorsFor('.replay-steps')[0];
      expect(base.body).not.toMatch(/#[0-9a-fA-F]{3,8}\b/);
      expect(base.body).not.toMatch(/rgba?\(/);
    });
  });

  describe('embedded (PropertyPanel) DOM contract', () => {
    const replayData: ReplayStep[] = [
      { type: 'started', id: 'n1' },
      { type: 'execute', id: 'n1' },
      { type: 'execute', id: 'n2' },
    ];
    const nodes: Node[] = [
      { id: 'n1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Event' } },
      { id: 'n2', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Action' } },
    ];

    // The step sections only render once a DATA entry is selected.
    const replayEntries: ReplayEntry[] = [
      {
        model_id: 'm1',
        component_id: 'n1',
        history: replayData,
        timestamp: 1700000000,
        user: 'tester',
        ip: '127.0.0.1',
        url: '/test',
      },
    ];

    const renderEmbedded = () =>
      render(
        <ReplayPanelContent
          replayData={replayData}
          isReplayMode={true}
          onToggleReplay={() => {}}
          onSelectStep={() => {}}
          currentStep={0}
          nodes={nodes}
          edges={[]}
          replayEntries={replayEntries}
          selectedEntryIndex={0}
        />
      );

    it('renders the .replay-steps container', () => {
      const { container } = renderEmbedded();
      expect(container.querySelector('.replay-steps')).toBeInTheDocument();
    });

    it('renders it WITHOUT a .replay-panel ancestor — the exact bug condition', () => {
      const { container } = renderEmbedded();
      const steps = container.querySelector('.replay-steps')!;
      expect(steps.closest('.replay-panel')).toBeNull();
    });

    it('still renders the step rows that need to be scrolled through', () => {
      const { container } = renderEmbedded();
      const steps = container.querySelector('.replay-steps')!;
      expect(steps.querySelectorAll('.replay-step').length).toBe(replayData.length);
    });
  });
});
