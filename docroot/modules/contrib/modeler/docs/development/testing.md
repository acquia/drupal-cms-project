# Testing

The modeler has comprehensive test coverage across three testing layers. For
complete references, see `ui/docs/e2e-testing.md` and
`ui/docs/component-development.md`.

## Test layers

| Layer | Tool | Location | Count |
|-------|------|----------|-------|
| **Unit tests** | Jest + React Testing Library | `src/__tests__/`, `src/**/__tests__/` | ~2500 |
| **E2E tests** | Playwright | `tests/e2e/` | ~116 |
| **Accessibility** | axe-playwright via Storybook | `.storybook/test-runner.ts` | ~165 stories |

## Running tests

```bash
# Unit tests
npm test                              # All unit tests
npm test -- path/to/test.test.tsx     # Single test file
npm run test:watch                    # Watch mode
npm run test:coverage                 # Coverage report

# E2E tests
npm run e2e                           # All E2E tests
npm run e2e:ui                        # Interactive mode

# Accessibility tests
npm run test-storybook:ci             # Build + serve + test (all-in-one)
npm run storybook                     # Start Storybook (port 6006)
npm run test-storybook                # Test running Storybook
```

## Unit testing patterns

```typescript
import { render, screen, fireEvent } from '@testing-library/react';
import MyComponent from '../MyComponent';

describe('MyComponent', () => {
  it('renders the title', () => {
    render(<MyComponent title="Test Title" />);
    expect(screen.getByText('Test Title')).toBeInTheDocument();
  });

  it('calls onAction when button is clicked', () => {
    const onAction = jest.fn();
    render(<MyComponent title="Test" onAction={onAction} />);
    fireEvent.click(screen.getByRole('button'));
    expect(onAction).toHaveBeenCalledTimes(1);
  });
});
```

## E2E testing patterns

E2E tests use a **Page Object pattern** for maintainability:

```typescript
import { ModelerPage } from './fixtures/ModelerPage';

test('can add a node via quick-add', async ({ page }) => {
  const modeler = new ModelerPage(page);
  await modeler.goto();

  await modeler.hoverNode('event_1');
  await modeler.clickQuickAdd();
  await modeler.selectComponent('Send email');

  await expect(modeler.getNodes()).toHaveCount(2);
});
```

See `ui/docs/e2e-testing.md` for the full mock system, test categories, and
CI integration.

## Accessibility testing

Every Storybook story is automatically tested with axe-core in both light and
dark mode. Tests check for WCAG AA violations including:

- Color contrast ratios
- Missing ARIA labels
- Keyboard accessibility
- Focus management

See `ui/docs/accessibility.md` for the complete accessibility testing setup.

## Quality metrics

The project maintains high quality standards:

- ~2500 unit tests
- ~116 E2E tests
- ~165 Storybook stories (all a11y-tested)
- Zero TypeScript errors
- Zero ESLint violations
