/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/scheme_switcher/scheme_switcher.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

(() => {
  'use strict';

  const getStoredScheme = () => localStorage.getItem('scheme');
  const setStoredScheme = (scheme) => localStorage.setItem('scheme', scheme);

  const getPreferredScheme = () => {
    const stored = getStoredScheme();
    if (stored) {
      return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light';
  };

  const applyScheme = (scheme) => {
    document.documentElement.setAttribute('data-scheme', scheme);
  };

  const updateSchemeSwitcherUI = (scheme) => {
    const switchers = document.querySelectorAll('.scheme-switcher__controller');
    if (switchers.length) {
      switchers.forEach((switcher) => {
        switcher.checked = scheme === 'dark';
      });
    }
  };

  // Apply the preferred scheme on initial load
  const currentScheme = getPreferredScheme();
  applyScheme(currentScheme);
  updateSchemeSwitcherUI(currentScheme);

  // Initialize event listeners
  const init = () => {
    const schemeSwitchers = document.querySelectorAll(
      '.scheme-switcher__controller',
    );
    if (schemeSwitchers.length) {
      schemeSwitchers.forEach((schemeSwitcher) => {
        schemeSwitcher.addEventListener('change', (e) => {
          const newScheme = e.target.checked ? 'dark' : 'light';
          setStoredScheme(newScheme);
          applyScheme(newScheme);
        });
      });
    }

    window
      .matchMedia('(prefers-color-scheme: dark)')
      .addEventListener('change', (e) => {
        if (!getStoredScheme()) {
          const systemScheme = e.matches ? 'dark' : 'light';
          applyScheme(systemScheme);
          updateSchemeSwitcherUI(systemScheme);
        }
      });
  };

  window.addEventListener('DOMContentLoaded', () => {
    init();
  });
})();
