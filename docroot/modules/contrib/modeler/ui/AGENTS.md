# Agent Instructions

High-level guidance for AI agents working with the Workflow Modeler codebase. Detailed documentation is in the `docs/` directory.

## Quick Start Commands

```bash
# All commands run from the ui/ directory (the working directory for Claude Code)

# Full development pipeline
npm install --include=dev
npm run dev              # Runs: lint → typecheck → build

# Individual commands
npm run lint             # ESLint code quality
npm run lint:fix         # Auto-fix violations
npm run type-check       # TypeScript validation
npm run build            # Development bundles
npm run build:production # Production bundles

# Testing
npm test                 # All unit tests
npm test -- path/to/test.test.tsx    # Run single test
npm run test:watch       # Watch mode for development
npm run test:coverage    # Coverage report
npm run e2e              # End-to-end tests
npm run e2e:ui          # Interactive E2E mode

# Storybook
npm run storybook           # Component documentation (port 6006)
npm run test-storybook      # Accessibility audits (needs running Storybook)
npm run test-storybook:ci   # Build + serve + test (all-in-one, no server needed)
```

## Code Style Guidelines

### TypeScript Standards
- **Strict Mode**: Zero TypeScript errors required
- **No `any` Types**: Use proper interfaces, especially for API data
- **Component Props**: Always define interfaces for component props
- **Event Handlers**: Type all event parameters (MouseEvent, KeyboardEvent, etc.)
- **Async Functions**: Return `Promise<T>` type explicitly

```typescript
// ✅ Correct
interface Props {
  title: string;
  onSubmit?: () => void;
}

const MyComponent: React.FC<Props> = ({ title, onSubmit }) => {
  // ...
};
```

### Language: American English Only
- **All code, comments, and documentation must use American English spelling** (e.g., `color`, `neighboring`, `optimize`, `center`, `behavior`).
- Do NOT use British English variants.
- The project uses cspell for spell-checking, which flags British spellings as errors.

### Import Organization
- React/ReactFlow imports first
- External libraries second
- Internal modules third (relative imports)
- Use `.ts`/`.tsx` extensions for internal imports

```typescript
import React from 'react';
import { Node, Edge } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import { sanitizeHtml } from '../utils/sanitize';
```

### ESLint Rules (Zero Violations Policy)
- Auto-fix with `npm run lint:fix`
- No unused variables (prefix unused with `_`)
- No `debugger` statements
- React hooks rules enforced
- Custom i18n rule: Wrap user-facing strings with `t()`
- Custom viewport rule: `setCenter()` must always include `zoom` (see below)

```typescript
import { t } from '../utils/translation';

// ✅ Correct
<button>{t('Save Changes')}</button>
<div title={t('Click to expand')}></div>

// ❌ Wrong
<button>Save Changes</button>
```

### Viewport Operations (No Invasive Pan/Zoom)
When panning or zooming programmatically after adding/inserting nodes, follow these rules:
- **Never call `setCenter()` without an explicit `zoom` option.** ReactFlow defaults to maxZoom when `zoom` is omitted, causing jarring zoom jumps. The custom ESLint rule `viewport/no-bare-set-center` enforces this at lint time.
- **Preserve the user's current zoom level** by passing `zoom: getZoom()`.
- **Only pan when the target node is off-screen.** Check the viewport bounds before calling `setCenter()`. If the node is already visible, do nothing.
- **Avoid `fitView()` for single-node operations** — it changes the zoom level. Prefer `setCenter()` with the current zoom preserved.

```typescript
// ❌ Wrong — zoom defaults to maxZoom, jarring viewport jump
setCenter(x, y, { duration: 800 });

// ✅ Correct — preserves current zoom, only pans if off-screen
const currentZoom = getZoom();
const vp = getViewport();
// ... check if point is within viewport bounds ...
if (!isVisible) {
  setCenter(x, y, { zoom: currentZoom, duration: 800 });
}
```

### Store Patterns (Zustand)
- Use individual selectors from domain-specific stores, never destructure
- Import each store directly from its own file (e.g., `useGraphStore`, `useSelectionStore`)
- Avoid nodes/edges arrays in effect dependencies
- Create custom hooks for complex operations

```typescript
// ✅ Correct — domain-specific stores
const nodes = useGraphStore(s => s.nodes);
const selectedNode = useSelectionStore(s => s.selectedNode);
const isDarkMode = useUISettingsStore(s => s.darkMode);

// ❌ Wrong — destructuring or nonexistent monolithic store
const { nodes, selectedNode } = useGraphStore();
```

### Naming Conventions
- **Components**: PascalCase (MyComponent.tsx)
- **Hooks**: camelCase with `use` prefix (useMyHook.ts)
- **Utilities**: camelCase (myUtility.ts)
- **Constants**: UPPER_SNAKE_CASE in `constants/dimensions.ts`
- **CSS Variables**: `--modeler-*` namespace only

### Error Handling
- Use try-catch for all async operations
- Validate external data with `utils/validation.ts`
- Report errors through `utils/errorReporting.ts`
- Never use innerHTML without `sanitizeHtml()`

```typescript
try {
  const data = await fetch('/api/data');
  const validated = validateApiResponse(await data.json());
  return validated;
} catch (error) {
  reportError(error);
  throw error;
}
```

### CSS & Styling
- **Never hardcode color values** in CSS rules — always use `--modeler-*` custom properties
- If no existing variable fits, **define a new one** in the `:root` (light) and `.modeler.dark-mode` (dark) blocks in `modeler.css`, then reference it with `var()`
- All styles scoped to `.modeler` class with `all: revert`
- WCAG AA contrast required (4.5:1 text, 3:1 non-text)
- Dark mode via `.modeler.dark-mode` class — hardcoded colors break dark mode

```css
/* ✅ Correct — uses variables, works in both modes */
.my-element {
  color: var(--modeler-color-text-primary);
  border: 1px solid var(--modeler-color-border-light);
  border-radius: var(--modeler-radius-md);
}

/* ❌ Wrong — hardcoded color, breaks in dark mode */
.my-element {
  color: #374151;
}
```

## Architecture

### State Management
- **Zustand Stores**: Domain-specific stores in `store/use*Store.ts` (import each directly)
  - `useGraphStore` — nodes, edges, graph mutations
  - `useSelectionStore` — selected node/edge (single + multi)
  - `useHistoryStore` — undo/redo with snapshot stack (max 50)
  - `usePanelStore` — panel collapse state
  - `useUISettingsStore` — dark mode, token dragging
  - `useContextStore` — contexts, selectedContextId
  - `useFilterStore` — visibleStartNodeIds
  - `useModelStore` — model data, metadata
  - And others: `useComponentStore`, `useLabelStore`, `useErrorStore`, `useConfigModalStore`
- **Viewport**: `useViewportActions` hook (not a store) — see `docs/viewport-management.md`
- **No React Flow State**: Use store selectors only
- **ReactFlow Integration**: Canvas component handles flow-specific logic

### Key Files
- **App Root**: `App.tsx` - Error boundaries, dark mode toggle
- **Flow Orchestrator**: `Flow.tsx` - Main coordination (including `handleReplacePlaceholder`, `validateBeforeSave`)
- **Canvas**: `FlowCanvas.tsx` - ReactFlow integration
- **Toolbars**: `Toolbar.tsx` (main), `CanvasToolbar.tsx` (canvas-level), `ToolbarMenu.tsx` (kebab menu)
- **Node Components**: `nodes/CustomNode.tsx`, `nodes/StartNode.tsx`, `nodes/GatewayNode.tsx`, `nodes/SubprocessNode.tsx`, `nodes/PlaceholderNode.tsx` (condition-first authoring)
- **Quick-add**: `QuickAddButton.tsx` (with type filter), `QuickAddPopup.tsx` (shared popup with `TypeFilterOption` support)
- **Stores**: `store/use*Store.ts` - Domain-specific Zustand stores
- **Types**: `types/settings.ts` - Shared TypeScript interfaces

### Testing Strategy
- **Unit Tests**: Jest + React Testing Library in `__tests__/` directories
- **E2E Tests**: Playwright with page object pattern
- **Accessibility**: axe-core audits via Storybook
- **Coverage**: Maintain high coverage across all components

### Documentation Screenshots

The mkdocs documentation at `docs/` references screenshots in `docs/assets/screenshots/`. These are generated automatically by a Playwright spec that drives the modeler through each UI state and captures a PNG.

To refresh screenshots after UI changes:

```bash
# 1. Build the modeler (the E2E test server serves from dist/)
npm run build

# 2. Run the screenshot spec, which writes PNG files to tests/screenshots/
npx playwright test --config tests/playwright.config.ts tests/e2e/screenshots.spec.ts

# 3. Convert only the screenshots whose UI changed to the docs' JPEG format
python3 - <<'PY'
from PIL import Image

changed = ("review-model", "step-data", "replay-panel", "replay-load")
for name in changed:
    with Image.open(f"tests/screenshots/{name}.png") as image:
        image.convert("RGB").save(f"../docs/assets/screenshots/{name}.jpg", "JPEG", quality=85, optimize=True)
PY
```

The spec lives at `tests/e2e/screenshots.spec.ts`. It uses the same mock server and Page Object (`ModelerPage`) as the regular E2E tests, so no real Drupal backend is needed. Only overwrite the documentation screenshots whose UI changed.

### Security Requirements
- **XSS Prevention**: All HTML through `sanitizeHtml()` or `sanitizeTokenHtml()`
- **CSRF Protection**: Use `fetchValidatedCsrfToken()` for API calls
- **Input Validation**: Validate all external data through `utils/validation.ts`
- **Token Security**: Validate drag-and-drop tokens properly

## npm/npx Execution

When the `remote-npm` skill is installed, **always use it** for running any npm or npx command. The skill automatically routes commands to a Docker container on a remote host via SSH. If the skill is not installed, run npm/npx commands directly on the local machine.

All commands in this document and in the `docs/` directory are written as plain `npm`/`npx` invocations. The `remote-npm` skill transparently wraps them when present — no command changes are needed.

See `docs/remote-execution.md` for details on how remote execution works.

## Development Workflow

1. **Quality Gates**: Every `npm run dev` includes lint + typecheck + build
2. **Zero Tolerance**: No TypeScript errors or ESLint violations allowed
3. **Auto-fix Available**: Run `npm run lint:fix` for common issues
4. **Single Test Pattern**: `npm test -- path/to/test.test.tsx`
5. **Documentation**: Storybook stories required for new components

Remember: This is a Drupal 11.3+/12.x module providing a modern workflow modeler UI. All code must meet enterprise standards with full WCAG AA accessibility compliance.
