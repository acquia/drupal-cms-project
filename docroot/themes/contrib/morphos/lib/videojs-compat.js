/*!
 * Project Name: Morphos (morpht/morphos)
 * File: lib/videojs-compat.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * VideoJS compatibility shim.
 *
 * Patches the deprecated videojs.createTimeRange and videojs.createTimeRanges
 * so that plugins still using them (e.g. videojs-youtube@3) no longer trigger
 * the console deprecation warning. Both aliases are defined as getters in
 * VideoJS 8 that log a WARN on every access.
 *
 * Safe to remove once all plugins migrate to videojs.time.createTimeRanges
 * (VideoJS 9+).
 */

(function () {
  'use strict';

  if (typeof videojs === 'undefined') {
    return;
  }

  // Grab the real function once (this triggers the getter/warning a single
  // time during page load). Then overwrite both deprecated getter-based
  // aliases with plain properties so subsequent accesses are silent.
  var fn =
    (videojs.time && videojs.time.createTimeRanges) || videojs.createTimeRanges;

  if (fn) {
    videojs.createTimeRanges = fn;
    videojs.createTimeRange = fn;
  }
})();
