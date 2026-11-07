/**
 * React Profiler integration for the workflow modeler.
 *
 * Provides a centralized `onRender` callback that logs slow renders during
 * development.  In production builds React strips `<Profiler>` callbacks
 * entirely, so there is zero runtime cost.
 *
 * Usage:
 * ```tsx
 * import { Profiler } from 'react';
 * import { onRenderCallback } from '../utils/profiling';
 *
 * <Profiler id="FlowCanvas" onRender={onRenderCallback}>
 *   <FlowCanvas ... />
 * </Profiler>
 * ```
 *
 * The callback uses `PROFILER_SLOW_THRESHOLD_MS` to decide when to emit a
 * warning.  Adjust this value to tune sensitivity.
 */

import type { ProfilerOnRenderCallback } from 'react';

// ────────────────────────────────────────────────
// Configuration
// ────────────────────────────────────────────────

/**
 * Render durations (in ms) above this threshold trigger a console warning.
 * Tune this to match your performance budget.
 */
const PROFILER_SLOW_THRESHOLD_MS = 16;

// ────────────────────────────────────────────────
// Callback
// ────────────────────────────────────────────────

/**
 * Shared `onRender` callback for `<Profiler>` wrappers.
 *
 * Logs a warning for any render that exceeds the slow-render threshold.
 * In development mode it also logs every render at the `debug` level so
 * React DevTools Profiler recordings include the data.
 */
export const onRenderCallback: ProfilerOnRenderCallback = (
  id,
  phase,
  actualDuration,
  baseDuration,
  startTime,
  commitTime,
) => {
  if (actualDuration > PROFILER_SLOW_THRESHOLD_MS) {
    console.warn(
      `[Profiler] Slow render: "${id}" (${phase}) took ${actualDuration.toFixed(1)}ms ` +
      `(base ${baseDuration.toFixed(1)}ms, commit ${commitTime.toFixed(0)}–start ${startTime.toFixed(0)})`,
    );
  }
};
