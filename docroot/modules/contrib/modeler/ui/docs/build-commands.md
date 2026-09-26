# Build and Quality Pipeline

Development workflows with quality gates and proper tooling.

## Development Workflow

### Quick Start Commands
```bash
# Full development pipeline
npm run dev              # lint → typecheck → build

# Individual commands
npm run lint             # ESLint code quality
npm run lint:fix         # Auto-fix violations
npm run type-check       # TypeScript validation
npm run build            # Development bundles
npm run build:production # Production bundles
npm run build:novalidate  # Build without validation

# Standalone viewer (includes main bundle + viewer bundle)
npm run build:standalone            # Development
npm run build:standalone:production # Production (minified)
```

### Quality Pipeline
```bash
# npm run dev executes:
1. Dependencies Validation ✅
2. TypeScript Type Checking ✅  
3. ESLint Code Quality ✅
4. esbuild Bundling ✅
```

## Code Quality Standards

### ESLint Configuration
```bash
# Check code quality
npm run lint

# Auto-fix issues
npm run lint:fix

# Check specific files
npx eslint src/components/Flow.tsx

# Quiet mode (errors only)
npm run lint -- --quiet
```

### TypeScript Validation
```bash
# Type checking
npm run type-check

# Integrated in build
npm run build  # Includes type checking
```

### Quality Gates
- **TypeScript**: Zero errors required
- **ESLint**: Zero violations (auto-fix available)
- **Build**: Must pass quality checks

## Testing Commands

### Unit Testing
```bash
# Run all tests
npm test

# Watch mode (development)
npm run test:watch

# Coverage report
npm run test:coverage

# CI mode
npm run test:ci
```

### E2E Testing
```bash
# Run all E2E tests
npm run e2e

# Interactive UI mode
npm run e2e:ui

# Headed browsers (visible)
npm run e2e:headed

# Debug mode
npm run e2e:debug

# View test report
npm run e2e:report
```

### Storybook
```bash
# Start development server
npm run storybook        # http://localhost:6006

# Build static site
npm run build-storybook

# Accessibility audits
npm run test-storybook
npm run test-storybook:a11y   # Ignores expected console errors
```

## Build Configuration

### Development Mode
```bash
npm run build  # OR ./build.sh
```
- Unminified code with sourcemaps
- ~1.7MB JS bundle, ~80KB CSS
- `NODE_ENV=development`
- Generates `.map` files
- Full quality pipeline included

### Production Mode
```bash
npm run build:production  # OR ./build.sh --production
```
- Minified and tree-shaken code
- ~399KB JS bundle, ~47KB CSS (75% smaller)
- `NODE_ENV=production`
- No sourcemaps
- Full quality pipeline included

### No-Validate Mode
```bash
npm run build:novalidate  # OR ./build.sh --no-validate
```
- Skips ESLint and TypeScript validation
- Useful for quick iteration
- Same output as development mode

### Standalone Viewer Mode
```bash
npm run build:standalone             # OR ./build.sh --standalone
npm run build:standalone:production  # OR ./build.sh --standalone --production
```
- Builds the main bundle **plus** the standalone viewer bundle
- Standalone bundle is a self-contained IIFE (~2MB dev, smaller minified)
- Outputs `dist/modeler-viewer.bundle.js` and `dist/modeler-viewer.bundle.css`
- The viewer can be embedded in any web page without a Drupal backend
- See `docs/export-system.md` → "Standalone Viewer" for embedding instructions

## Build Script Details

### Dependencies Validation
```bash
# Automatic checks in build.sh
- [ ] node_modules directory exists
- [ ] Critical packages installed: react, react-dom, reactflow, esbuild, typescript, zustand, dompurify
- [ ] Exits with clear error if validation fails
```

### Quality Integration
```bash
# TypeScript checking
npx tsc --noEmit  # Fails on errors

# ESLint checking
npx eslint src --ext .ts,.tsx --format stylish  # Auto-fix available

# Build bundles
./build.sh --mode development
```

## IDE Integration

### VSCode Configuration
```json
// .vscode/settings.json
{
  "editor.formatOnSave": true,
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": true
  },
  "typescript.preferences.preferTypeOnlyAutoImports": true,
  "eslint.workingDirectories": ["src"]
}
```

### Extensions
- **ESLint**: Real-time linting and auto-fix
- **TypeScript Importer**: Automatic import organization
- **Prettier**: Code formatting (if configured)
- **Auto Rename Tag**: JSX tag renaming

## Quality Enforcement

### Pre-commit Hooks (Optional)
```bash
#!/bin/sh
# .git/hooks/pre-commit
npm run lint
npm run type-check
npm test  # Unit tests
```

### CI Pipeline
```yaml
# GitLab CI example
reactapp build:
  stage: build
  script:
    - cd ui
    - npm ci
    - npm run lint
    - npm run type-check  
    - npm run build:production
  artifacts:
    paths:
      - dist/
```

## Error Handling

### Common Build Issues
```bash
# TypeScript errors
npm run type-check  # Detailed error report

# ESLint violations
npm run lint:fix      # Auto-fix common issues

# Dependency issues
npm install --include=dev  # Reinstall all dependencies

# Build permission issues
chmod +x build.sh  # Make build script executable
```

### Debug Mode
```bash
# Verbose build output
DEBUG=true npm run build

# Build without cache
npm run build -- --no-cache
```

## Performance Monitoring

### Build Performance
```bash
# Time the build process
time npm run build

# Bundle analysis
npm run build:analyze  # If available
```

### Development Server
```bash
# Fast refresh for development
npm run dev  # Includes watch mode

# Storybook hot reload
npm run storybook
```

## Quality Metrics

### Current Standards
- **TypeScript**: 0 errors (strict mode)
- **ESLint**: 0 violations
- **Unit Tests**: 2502 passing tests
- **E2E Tests**: 116 passing tests
- **Accessibility**: 165 Storybook stories pass axe-core audit
- **Coverage**: High coverage across utilities, hooks, components

### Coverage Reports
```bash
# Generate coverage
npm run test:coverage

# View coverage report
open coverage/lcov-report/index.html
```

## Troubleshooting

### Common Issues
1. **Build fails with TypeScript errors**
   - Run `npm run type-check` for details
   - Check import paths and type definitions
   - Ensure all interfaces are properly typed

2. **ESLint violations blocking build**
   - Run `npm run lint:fix` for auto-fixes
   - Review remaining violations manually
   - Update .eslintrc if rules are too strict

3. **Test failures in CI**
   - Check CI environment differences
   - Ensure all test dependencies are installed
   - Review test timeouts and async handling

4. **Memory issues during build**
   - Increase Node.js memory limit: `NODE_OPTIONS="--max-old-space-size=4096"`
   - Check for memory leaks in test code
   - Use `--no-cache` flag if cache is corrupted

