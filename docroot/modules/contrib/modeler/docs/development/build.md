# Build & Quality

The modeler uses a rigorous build pipeline with multiple quality gates. For the
complete reference, see `ui/docs/build-commands.md` and `ui/docs/code-quality.md`.

## Build pipeline

The `npm run dev` command runs the full development pipeline:

![Build pipeline: lint, then typecheck, then build](../assets/diagrams/build-pipeline.svg)

All three steps must pass with zero errors.

## Commands reference

### Development

```bash
npm run dev              # Full pipeline: lint + typecheck + build
npm run build            # Development build only
npm run build:production # Production build (minified)
npm run build:no-validate # Build without lint/typecheck
```

### Quality checks

```bash
npm run lint             # ESLint (zero violations required)
npm run lint:fix         # Auto-fix ESLint violations
npm run type-check       # TypeScript validation (zero errors required)
```

### Testing

```bash
npm test                 # All unit tests
npm run test:coverage    # Unit tests with coverage report
npm run e2e              # Playwright E2E tests
npm run test-storybook:ci # Storybook accessibility tests
```

## Quality gates

| Gate | Tool | Threshold |
|------|------|-----------|
| **Linting** | ESLint | Zero violations |
| **Type checking** | TypeScript (strict mode) | Zero errors |
| **Unit tests** | Jest | All passing |
| **E2E tests** | Playwright | All passing |
| **Accessibility** | axe-core | Zero violations |

## ESLint rules

Key rules enforced:

- **No unused variables**: Prefix unused parameters with `_`.
- **No `any` types**: Use proper TypeScript interfaces.
- **React hooks rules**: Enforce hook dependency arrays.
- **i18n rule**: All user-facing strings must use `t()`.
- **No `debugger` statements**.

## TypeScript configuration

The project uses strict TypeScript mode with:

- `strict: true`
- `noImplicitAny: true`
- `strictNullChecks: true`
- All imports require `.ts`/`.tsx` extensions for internal modules.

## CSS standards

- **98 CSS custom properties** defined in `:root` and `.modeler.dark-mode`.
- **No hardcoded colors** -- always use `--modeler-*` variables.
- All styles scoped to `.modeler` class.
- WCAG AA contrast ratios required.

See `ui/docs/code-quality.md` for the complete CSS variable reference and
color palette.

## CI pipeline

The GitLab CI pipeline runs:

1. **Build**: Full `npm run dev` pipeline.
2. **Lint**: ESLint validation.
3. **Typecheck**: TypeScript validation.
4. **Unit tests**: Jest test suite.
5. **E2E tests**: Playwright browser tests.
6. **Accessibility**: axe-core audits via Storybook.
7. **Release**: Asset bundling for distribution.
