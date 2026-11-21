# Code Quality & Standards ✅

**Current Status (February 2026)**: Enterprise-grade code quality achieved with zero TypeScript errors, zero ESLint violations, and comprehensive standardization.

## Recent Achievements

### ✅ **TypeScript Error Resolution**
- **Fixed all 47 TypeScript errors** discovered during comprehensive type checking
- **Type system improvements**: Browser compatibility, ReactFlow integration, interface consistency
- **Function signatures**: All callback and state setter types properly defined
- **Component props**: Complete interface resolution across all components

### ✅ **ESLint Integration**
- **Zero linting violations** across entire JavaScript codebase
- **Comprehensive configuration** with React and JSX support
- **Build process integration** with graceful fallbacks
- **Auto-fix capabilities** for development workflow
- **IDE integration** with VSCode auto-fix on save
- **i18n enforcement rule** for translation compliance

### ✅ **Constants Consolidation**
- **Centralized all hardcoded values** into typed constants in `dimensions.ts`
- **Timing constants**: Debounce delays, sync delays, animation durations
- **Dimension constants**: Node sizes, panel widths, layout spacing
- **Position constants**: Default coordinates, layout start positions

### ✅ **CSS Custom Properties**
- **98 CSS variables** defined in `.modeler` block of `modeler.css` with `all: revert` for Drupal isolation
- **Namespaced with `--modeler-` prefix** to prevent conflicts with Drupal's CSS custom properties
- **Color consolidation**: Reduced 91 unique hex colors to ~25 semantic variables
- **Categorized variables**: Text, background, border, primary, danger, warning, success, component types, data types, tokens, edges, shadows
- **Legacy cleanup**: Consolidated 3 competing color palettes (Tailwind, Material, legacy) into a unified system

## Quality Standards

### TypeScript Configuration
- **ES2020 target** for modern JavaScript features
- **Strict type checking** with comprehensive error detection
- **Browser environment** optimized (removed NodeJS dependencies)
- **ReactFlow compatibility** with proper import handling

### ESLint Rules
- **Code quality**: No unused variables, prefer const, no var
- **Error prevention**: No debugger, no duplicate imports, no unreachable code
- **React patterns**: JSX support with proper variable handling
- **Drupal integration**: Global variables properly declared
- **i18n enforcement**: Custom rule to enforce translation of user-facing strings

### Constants Architecture
```typescript
// Centralized in constants/dimensions.ts
export const TIMING = {
  DEBOUNCE_DELAY: 300,
  REPLAY_SYNC_DELAY: 150,
  CLEANUP_DELAY: 200,
  // ... all timing values
} as const;

export const NODE_DIMENSIONS = {
  DEFAULT_WIDTH: 200,
  DEFAULT_HEIGHT: 100,
  // ... all node dimensions  
} as const;

export const LAYOUT = {
  DEFAULT_POSITION_X: 100,
  DEFAULT_POSITION_Y: 100,
  // ... all layout constants
} as const;
```

## Build Quality Pipeline

### Development Workflow
```bash
npm run dev  # Full quality pipeline:
├── 1. ESLint code quality check ✅
├── 2. TypeScript type checking ✅  
├── 3. esbuild compilation ✅
└── 4. CSS bundling ✅
```

### Quality Gates
- **TypeScript**: Zero errors required for clean build
- **ESLint**: Zero violations with auto-fix available
- **Build process**: Graceful degradation if tools unavailable
- **Error reporting**: Detailed feedback with fix suggestions

## Development Tools

### ESLint Configuration
The ESLint configuration (`eslint.config.js`, flat config format) provides comprehensive code quality checking:

**Enabled Rules:**
- **TypeScript**: Comprehensive TypeScript linting with warnings for `any` types
- **React**: Modern React patterns with hooks support
- **Code Quality**: No unused variables, prefer const, no var, etc.
- **Error Prevention**: No debugger statements, duplicate imports, unreachable code

**Key Settings:**
- **React version detection**: Automatically detects React version
- **JSX support**: Full JSX/TSX support without requiring React imports
- **Modern JavaScript**: ES2020 syntax support
- **Browser environment**: Configured for browser-based development
- **Drupal globals**: Properly declared for Drupal integration

### IDE Integration

**VSCode Integration:**
- **Auto-fix on save** for ESLint violations
- **Real-time TypeScript** error detection and ESLint feedback
- **Auto-import** for TypeScript modules
- **Format on save** with consistent code style
- **Real-time error display** in problems panel

**Other IDEs:**
- **WebStorm**: Built-in ESLint support
- **Vim/Neovim**: Use coc-eslint or similar plugins
- **Sublime Text**: SublimeLinter-eslint package

## Internationalization (i18n) Enforcement

### Custom ESLint Rule: `i18n/no-untranslated-strings`

A custom ESLint rule enforces that all user-facing strings are wrapped with the `t()` translation function. This ensures full i18n compliance with Drupal's translation system.

**Location:** `eslint-rules/no-untranslated-strings.js`

**What it checks:**
- JSX text content (e.g., `<button>Save</button>`)
- Translatable JSX attributes (`title`, `alt`, `placeholder`, `aria-label`, etc.)
- String literals that look like user-facing text

**What it ignores (technical strings):**
- CSS values (`1px solid #ccc`, `0 auto`, animation values)
- Keyboard key names (`Escape`, `Enter`, `Shift`)
- Comparison operands (`category === 'Events'`)
- Object keys and switch cases
- Strings inside `console.*`, `Error()`, `includes()`, etc.
- Test files and Storybook stories
- Technical identifiers (camelCase, snake_case, kebab-case)
- URLs, file paths, MIME types, UUIDs

**Configuration options:**
```javascript
'i18n/no-untranslated-strings': ['warn', {
  ignoreAttributes: [],    // Additional attributes to ignore
  ignoreFunctions: [],     // Additional functions to ignore
  ignorePatterns: [],      // Additional regex patterns to ignore
}]
```

**Using the translation function:**
```typescript
import { t } from '../utils/translation';

// Simple translation
<button>{t('Save')}</button>

// With placeholder
<span>{t('Hello @name', { '@name': userName })}</span>

// In attributes
<div title={t('Click to expand')}></div>
```

**Translation utility (`utils/translation.ts`):**
- Wraps `Drupal.t()` for translations
- Falls back to string interpolation when Drupal is unavailable (tests, Storybook)
- Supports all Drupal.t() placeholder formats (`@`, `%`, `!`)

### Available Commands
```bash
# Code quality commands
npm run lint          # Check code quality with ESLint
npm run lint:fix      # Auto-fix linting issues
npm run lint:watch    # Watch for changes and lint continuously

# Development pipeline
npm run type-check    # TypeScript type validation
npm run dev           # Full development pipeline (lint + typecheck + build)
npm run build            # Full build with quality checks
npm run build:production # Production build with optimizations
npm run build:novalidate # Build without lint/typecheck validation
```

### ESLint Integration Details

**Build Process Integration:**
1. **TypeScript type checking** runs first
2. **ESLint** runs second  
3. **Build process** continues regardless of warnings
4. **Build fails** only on critical errors

**Helpful Build Feedback:**
- ✅ Success messages when linting passes
- ⚠️ Warning messages with guidance on how to fix issues
- 🔧 Suggestions to run `npm run lint:fix` for auto-fixable issues

**Common Usage:**
```bash
# Focus on errors only during development
npm run lint -- --quiet  # Show only errors, suppress warnings

# Fix issues automatically
npm run lint:fix

# Check specific files
npx eslint src/components/Flow.tsx
```

## Benefits

### Code Reliability
- **Compile-time error detection** instead of runtime failures
- **Type safety** across all component interfaces
- **Consistent patterns** throughout codebase

### Developer Experience
- **IDE support** with intelligent autocompletion
- **Refactoring safety** with automatic type checking
- **Self-documenting code** through type annotations

### Maintainability
- **Centralized constants** for easy value updates
- **Standardized code style** across all files
- **Quality enforcement** through build pipeline

## CSS Encapsulation

The modeler's styles are fully encapsulated to prevent interference from the host page's CSS. This is critical both for Drupal admin themes and for the standalone viewer embedded on arbitrary sites (e.g., mkdocs-material documentation).

### Layer 1: `all: revert` on `.modeler`

The root `.modeler` element uses `all: revert` to reset all inherited properties to browser defaults. This prevents the host page's global rules from leaking into the modeler's root.

### Layer 2: Targeted descendant resets

`all: revert` does **not** cascade to descendants. A blanket `all: revert` on `.modeler *` would undo ReactFlow's own CSS (which lives in the same cascade origin), so that approach is not viable. Instead, targeted reset rules at the top of `modeler.css` handle the most commonly affected elements:

| Selector | What it fixes | Specificity |
|----------|---------------|-------------|
| `.modeler svg:where(:not(.react-flow svg))` | Restores Feather Icon defaults (`width: 1em`, `height: 1em`, `fill: none`, `stroke: currentColor`, etc.). The `:where()` wrapper zeros the specificity contribution so more-specific rules in our stylesheet still win. The `:not()` guard excludes ReactFlow canvas SVGs. | (0,1,1) |
| `.modeler h1` through `.modeler h6` | Reverts `font-size`, `font-weight`, `line-height`, `margin`, `padding`, `letter-spacing`, `color` to browser defaults. | (0,1,1) |
| `:where(.modeler) img, :where(.modeler) video` | Reverts `max-width` and `height` constraints. | (0,0,1) |

These resets live before the `.modeler { all: revert; ... }` block in the file so they are available regardless of source order.

### Adding new encapsulation rules

When a new host-page conflict is discovered:

1. Identify the conflicting host-page selector and which elements it affects.
2. Add a scoped reset rule in the "CSS ENCAPSULATION" section at the top of `modeler.css`.
3. Use `:where()` wrappers where possible to keep specificity low.
4. Add a `:not()` guard to exclude ReactFlow elements if the selector could match them.
5. Document the rationale in a CSS comment.

## CSS Custom Properties

All design tokens are defined as CSS custom properties in the `.modeler` block at the top of `src/styles/modeler.css`, scoped to the modeler container with `all: revert` to prevent Drupal CSS from leaking in. All variables are namespaced with the `--modeler-` prefix to avoid conflicts. The file uses **928 `var()` references** across ~5000 lines, with only 4 hex colors remaining outside `.modeler` (all technically constrained).

### Variable Categories

#### Colors (~65 variables)

| Category | Prefix | Example | Count |
|----------|--------|---------|-------|
| Text colors | `--modeler-color-text-*` | `--modeler-color-text-primary`, `--modeler-color-text-secondary` | 5 |
| Backgrounds | `--modeler-color-bg-*` | `--modeler-color-bg-primary`, `--modeler-color-bg-surface`, `--modeler-color-bg-muted` | 6 |
| Borders | `--modeler-color-border-*` | `--modeler-color-border-light`, `--modeler-color-border-default`, `--modeler-color-border-hover` | 4 |
| Primary blue | `--modeler-color-primary*` | `--modeler-color-primary`, `--modeler-color-primary-hover`, `--modeler-color-primary-light` | 4 |
| Interactive blue | `--modeler-color-interactive*` | `--modeler-color-interactive`, `--modeler-color-selection` | 4 |
| Danger/error | `--modeler-color-danger*` | `--modeler-color-danger`, `--modeler-color-danger-hover`, `--modeler-color-danger-soft` | 7 |
| Warning/amber | `--modeler-color-warning*` | `--modeler-color-warning`, `--modeler-color-warning-light` | 6 |
| Success/green | `--modeler-color-success*` | `--modeler-color-success`, `--modeler-color-success-drop` | 6 |
| Component types | `--modeler-color-type-*` | `--modeler-color-type-event`, `--modeler-color-type-action-light` | 12 |
| Data types | `--modeler-color-data-*` | `--modeler-color-data-string`, `--modeler-color-data-number` | 4 |
| Tokens | `--modeler-color-token-*` | `--modeler-color-token-bg`, `--modeler-color-token-text` | 5 |
| Edges | `--modeler-color-edge-*` | `--modeler-color-edge-default`, `--modeler-color-edge-stroke` | 2 |
| Accents | `--modeler-color-accent-*` | `--modeler-color-accent-indigo`, `--modeler-color-accent-violet` | 5 |
| Shadows | `--modeler-shadow-*`, `--modeler-color-shadow-*` | `--modeler-shadow-sm`, `--modeler-color-shadow-light` | 8 |
| Collapse widget | `--modeler-color-collapse-*` | `--modeler-color-collapse-line`, `--modeler-color-collapse-hover` | 3 |

#### Typography (~10 variables)

| Variable | Value | Usage |
|----------|-------|-------|
| `--modeler-font-size-xxs` | 9px | Data truncated text |
| `--modeler-font-size-xs` | 10px | Data values, token counts |
| `--modeler-font-size-sm` | 11px | Step details, annotations |
| `--modeler-font-size-md` | 12px | Labels, controls, badges |
| `--modeler-font-size-base` | 13px | Component text, step labels |
| `--modeler-font-size-lg` | 14px | Body text, inputs |
| `--modeler-font-size-xl` | 16px | Section headings |
| `--modeler-font-size-2xl` | 18px | Dialog headings |
| `--modeler-font-size-3xl` | 20px | Large headings |
| `--modeler-font-size-4xl` | 24px | Documentation h1 |

#### Border Radius (~7 variables)

| Variable | Value | Usage |
|----------|-------|-------|
| `--modeler-radius-xs` | 2px | Micro elements |
| `--modeler-radius-sm` | 3px | Small controls |
| `--modeler-radius-md` | 4px | Buttons, inputs, badges |
| `--modeler-radius-lg` | 6px | Cards, panels, popups |
| `--modeler-radius-xl` | 8px | Modals, dialogs |
| `--modeler-radius-2xl` | 12px | Documentation popup |
| `--modeler-radius-full` | 50% | Circles (annotation icons) |

#### Icon Sizes (~1 variable)

| Variable | Value | Usage |
|----------|-------|-------|
| `--modeler-icon-size` | 16px | Annotation icon width/height |

#### Transitions (~6 variables)

| Variable | Value | Usage |
|----------|-------|-------|
| `--modeler-transition-fast` | `all 0.15s ease` | Quick UI feedback |
| `--modeler-transition-default` | `all 0.2s ease` | Standard interactions |
| `--modeler-transition-slow` | `all 0.3s ease` | Panel animations |
| `--modeler-duration-fast` | `0.15s` | Specific-property transitions |
| `--modeler-duration-default` | `0.2s` | Specific-property transitions |
| `--modeler-duration-slow` | `0.3s` | Specific-property transitions |

### Dark Mode Override Block

A complete `.modeler.dark-mode` CSS block overrides all 90+ color-related custom properties with dark-adapted values. The dark palette uses:
- **Backgrounds**: Gray-700 (`#374151`) through Slate-900 (`#0f172a`) for panels, surfaces, and canvas
- **Text**: Gray-200 (`#e5e7eb`) for primary, Gray-300 (`#d1d5db`) for secondary
- **Accents**: Brighter variants (e.g., blue-400, red-400, emerald-400) for visibility on dark backgrounds
- **Shadows**: Higher opacity (`0.3`-`0.4`) to remain visible
- **Semantic colors**: Adjusted danger, warning, success, and component type colors for dark background readability

The dark mode is activated by adding the `dark-mode` class to the `.modeler` container in `App.tsx`. All 928 `var()` references automatically pick up the overridden values.

### Usage Guidelines

**Always use CSS variables** for new CSS:
```css
/* Good */
.my-element {
  color: var(--modeler-color-text-primary);
  border: 1px solid var(--modeler-color-border-light);
  border-radius: var(--modeler-radius-md);
  font-size: var(--modeler-font-size-lg);
  transition: var(--modeler-transition-default);
}

/* For specific-property transitions, use duration variables */
.my-element {
  transition: background var(--modeler-duration-default), color var(--modeler-duration-default);
}

/* Bad - hardcoded values */
.my-element {
  color: #374151;
  border-radius: 4px;
  font-size: 14px;
  transition: all 0.2s ease;
}
```

**Remaining hardcoded values** are technically constrained:
- `#10b981`, `#ef4444` — CSS attribute selectors matching literal inline styles
- `#15803d` — keyframe animation endpoint (unique dark green)
- `#fbbf24` — SVG fill for favorite star icon
**Inline styles in JSX components** still use hex literals (e.g., edge SVG strokes, badges). These cannot use CSS variables directly. A future refactor could extract these to CSS classes.

## Accessibility Standards (WCAG AA)

### Color Contrast Requirements

All UI elements must meet WCAG AA color contrast minimums:

| Category | Ratio | Applies To |
|----------|-------|------------|
| **Normal text** (< 18px or < 14px bold) | **4.5:1** | Labels, descriptions, placeholders, token strings, counts |
| **Large text** (≥ 18px or ≥ 14px bold) | **3:1** | Headings, large buttons |
| **Non-text UI** (SC 1.4.11) | **3:1** | Borders, icons, edge strokes, button backgrounds |

### Approved Color Palette

These colors have been validated against their typical backgrounds:

**Text colors on white/light backgrounds:**
- `#374151` (gray-700) - Primary text, strong secondary elements
- `#4b5563` (gray-600) - Secondary text, descriptions, placeholders
- `#6b7280` (gray-500) - Only for text ≥ 18px or non-text elements

**Non-text UI on white/light backgrounds:**
- `#8b8b8b` - Borders, edge strokes (3.4:1 on white)
- `#6b7280` - Hover borders, icon fills (4.0:1 on white)
- `#d97706` - Annotation icon color (toggled off); `#92400e` background with `--modeler-color-text-on-dark` icon (toggled on/active)

**Accent/action colors:**
- `#e65100` - Quick-add event button (3.5:1 on white)
- `#bf360c` - Quick-add event hover state

### Checking Contrast Ratios

Use any of these tools to verify contrast before committing color changes:

- **Chrome DevTools**: Inspect element → Color picker shows contrast ratio
- **WebAIM Contrast Checker**: https://webaim.org/resources/contrastchecker/
- **axe DevTools**: Browser extension for automated audits

### Automated Enforcement

Color contrast is enforced automatically via axe-core audits:

```bash
# Run a11y audits against all Storybook stories
npm run storybook          # Start Storybook on port 6006
npm run test-storybook:a11y  # Run axe-core audits
```

The `reactapp a11y` GitLab CI job runs these audits on every push. See [E2E Testing](e2e-testing.md) for CI configuration details.

### Common Pitfalls

- **`#6b7280` on white** is only 4.0:1 — fails for normal-sized text (needs 4.5:1). Use `#4b5563` instead.
- **`#9ca3af` on white** is only 2.7:1 — fails for all uses. Use `#6b7280` for non-text or `#4b5563` for text.
- **`#ccc` on white** is only 1.6:1 — fails for borders that convey meaning. Use `#8b8b8b`.
- **Inline styles** in components (e.g., `style={{ border: '...' }}`) are easy to miss — search for hardcoded color values.

## Related Documentation

- **[Security](security.md)**: XSS prevention and input sanitization
- **[Build Commands](build-commands.md)**: Quality pipeline and development commands
- **[TypeScript](typescript.md)**: Type system implementation details
- **[E2E Testing](e2e-testing.md)**: Playwright E2E and Storybook a11y testing

The codebase now meets enterprise standards with comprehensive quality assurance, WCAG AA accessibility compliance, and zero technical debt.