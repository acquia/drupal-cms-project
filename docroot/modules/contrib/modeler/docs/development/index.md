# UI Development

This section provides guidelines for developers working on the Modeler's React
UI. The modeler is a TypeScript/React application built with
[React Flow](https://reactflow.dev/), [Zustand](https://github.com/pmndrs/zustand)
for state management, and [esbuild](https://esbuild.github.io/) for bundling.

!!! note "Source documentation"
    The detailed technical documentation lives in `ui/docs/` within the module
    source tree. This section provides an overview and links to the relevant
    files. When contributing to the UI, always refer to the `ui/docs/` files
    for the most up-to-date guidance.

## Technology stack

| Technology | Purpose |
|------------|---------|
| **React 18** | UI component library |
| **React Flow** | Canvas and node/edge rendering |
| **TypeScript** (strict mode) | Type-safe application code |
| **Zustand** | Lightweight state management |
| **esbuild** | Fast bundling for development and production |
| **Jest + React Testing Library** | Unit testing |
| **Playwright** | End-to-end testing |
| **Storybook** | Component documentation and visual testing |
| **axe-core** | Automated accessibility testing |
| **ESLint** | Code quality and style enforcement |

## Quick start

All commands run from the `ui/` directory:

```bash
# Install dependencies
npm install --include=dev

# Full development pipeline (lint + typecheck + build)
npm run dev

# Individual commands
npm run lint             # ESLint code quality
npm run lint:fix         # Auto-fix violations
npm run type-check       # TypeScript validation
npm run build            # Development bundles
npm run build:production # Production bundles

# Testing
npm test                 # All unit tests
npm run e2e              # End-to-end tests
npm run storybook        # Component documentation (port 6006)
```

## Sections

- [Architecture](architecture.md) -- patterns, state management philosophy, and
  Drupal integration
- [Component Development](components.md) -- how to create and test new components
- [State Management](state-management.md) -- Zustand store patterns and best
  practices
- [Testing](testing.md) -- unit tests, E2E tests, accessibility testing, and
  Storybook
- [Plugin API](plugin-api.md) -- extending the modeler with toolbar widgets,
  panels, and programmatic graph manipulation from other Drupal modules
- [Build & Quality](build.md) -- build pipeline, quality gates, and CI
  integration
- [Security](security.md) -- XSS prevention, CSRF, input validation

## Source documentation index

The following files in `ui/docs/` provide comprehensive technical reference:

| File | Topic |
|------|-------|
| `accessibility.md` | WCAG AA compliance, keyboard navigation, screen reader support, axe-core testing |
| `architecture-patterns.md` | Orchestrator pattern, SOLID principles, error handling, feature flags |
| `build-commands.md` | npm scripts, build pipeline, quality gates, IDE integration |
| `code-quality.md` | TypeScript configuration, ESLint rules, CSS custom properties, i18n |
| `component-development.md` | React component patterns, Storybook stories, testing templates |
| `e2e-testing.md` | Playwright E2E tests, page objects, mock system, CI integration |
| `integration.md` | Drupal integration, API endpoints, data formats, configuration forms |
| `keyboard-shortcuts.md` | Keyboard shortcuts system, modifier handling, cross-browser support |
| `performance.md` | Store selectors, memory management, CSS optimizations |
| `remote-execution.md` | Docker-based remote npm execution for CI |
| `replay-system.md` | Execution replay, bidirectional sync, test runner, hooks |
| `security.md` | XSS prevention, CSRF, input validation, clipboard encryption |
| `state-management.md` | Zustand store patterns, selection architecture, modal state |
| `typescript.md` | TypeScript configuration, type patterns, React Flow integration |
| `export-system.md` | Export formats, standalone viewer, view modes, SVG rendering |
| `ui-components.md` | Panel UI architecture, components, toolbar, modals, quick-add |

## Code standards

- **Zero TypeScript errors** required.
- **Zero ESLint violations** required.
- **WCAG AA accessibility** compliance required.
- **All user-facing strings** must be wrapped in `t()` for internationalization.
- **No hardcoded colors** in CSS -- use `--modeler-*` custom properties.
- **Dark mode support** required for all visual elements.
