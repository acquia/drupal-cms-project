# AGENTS.md

This file provides guidance to AI agents and developers working on this repository.

## Project Overview

This is an Acquia-hosted version of Drupal CMS for Acquia Cloud Platform.

It extends official Drupal CMS with optimizations, Acquia product integrations, and Acquia Trials-specific features.

Optimizations:
- None at this time, but we may add performance improvements in the future.

Acquia product integrations:
- single sign-on (Acquia ID)

Trials-specific features:
- trial countdown banner
- getting started checklist
- Cloud Platform promotional blocks.

**Stack:** Drupal 11, PHP 8.5, MySQL 8.0, Composer 2

## Key Concepts
- This respository is meant as a starting point for customers to build application. It is not an application itself.
- Nothing used to assist with development or testing of this repository should be  

## Repository Structure

```
├── docroot/                    # Web root (Drupal)
│   ├── core/                   # Drupal core (do not modify)
│   ├── modules/
│   │   ├── contrib/            # Contributed modules (managed by Composer)
│   │   └── custom/             # Custom Acquia modules (our code)
│   ├── themes/contrib/         # Contributed themes
│   ├── sites/default/
│   │   └── settings/           # Environment-specific settings
│   └── profiles/contrib/
├── recipes/                    # Drupal recipes (contrib and custom)
│   └── custom/acquia_trials/   # Bundles all trial modules
├── patches/                    # Core patches
├── .github/workflows/          # CI/CD
├── composer.json               # Dependencies
├── CONTRIBUTING.md             # Contribution guidelines
└── AGENTS.md                   # This file
```

## Custom Modules

All custom code lives in `docroot/modules/custom/`:

| Module | Purpose |
|--------|---------|
| `acquia_id` | OAuth2 SSO via Acquia ID (PKCE flow, token management, logout) |
| `acquia_trials_id` | Verifies Cloud application access during SSO login |
| `acquia_trials_countdown` | Trial expiration countdown banner (admin-only) |
| `acquia_trials_checklist` | Interactive onboarding task checklist |
| `acquia_trials_cloud_platform` | Promotional block for Cloud Platform features |

## Module Conventions

Each module follows this structure:
```
module_name/
├── module_name.info.yml        # Metadata (package: "Acquia" or "Acquia Trials")
├── module_name.module          # Hook implementations
├── module_name.services.yml    # Service definitions (parameters + services)
├── module_name.routing.yml     # Routes
├── module_name.libraries.yml   # CSS/JS asset libraries
├── composer.json               # Module-level dependencies
├── src/                        # PHP classes (PSR-4: Drupal\module_name\)
├── templates/                  # Twig templates
├── css/, js/                   # Frontend assets
└── tests/src/Unit|Kernel/      # Tests
```

## Architecture Patterns

### Service Parameters for Environment Config
Environment-specific values (URLs, keys) are defined as service parameters in `*.services.yml` and overridden dynamically via `ServiceProvider::alter()`. See `AcquiaIdServiceProvider.php` for the pattern.

### Environment Detection
Use `Acquia\Drupal\RecommendedSettings\Helpers\EnvironmentDetector` for environment checks (e.g., `EnvironmentDetector::isProdEnv()`). The key env var is `AH_SITE_ENVIRONMENT` (values: `prod`, `dev`, `test`).

### Hook Implementations
Hooks live in `*.module` files. Use `\Drupal::service()` for service access in procedural hook code. Always add cache contexts/tags when hook output varies by user or role (e.g., `$attachments['#cache']['contexts'][] = 'user.permissions'`).

### Frontend Assets
Libraries are declared in `*.libraries.yml` and attached via `hook_page_attachments()` or block `build()` methods. External scripts use `type: external` with `attributes: { async: true }`.

### Events
The OAuth2 flow uses Symfony events (`OAuth2AuthorizationEvent`) dispatched by `acquia_id` and subscribed to by `acquia_trials_id`.

## Coding Standards

- `declare(strict_types=1)` in all new PHP files
- PHPUnit attributes (`#[CoversClass]`, `#[Group]`) for tests
- 2-space indentation (PHP, YML, JS, CSS, Twig)
- LF line endings
- No trailing whitespace, insert final newline
- Drupal coding standards (Drupal CS)
- Commit messages: `TICKET-123 | Description` (no trailing period)

## Running Tests

**Unit tests** (no database needed):
```bash
cd docroot/core
../../vendor/bin/phpunit ../modules/custom/*/tests/src/Unit
```

**Kernel tests** (requires MySQL):
```bash
cd docroot/core
SIMPLETEST_DB="mysql://drupal:drupal@localhost:3306/drupal" \
  ../../vendor/bin/phpunit ../modules/custom/*/tests/src/Kernel
```

**Single test file:**
```bash
cd docroot/core
SIMPLETEST_DB="mysql://drupal:drupal@localhost:3306/drupal" \
  ../../vendor/bin/phpunit ../modules/custom/acquia_id/tests/src/Kernel/Controller/OAuth2ControllerTest.php
```

**Filter specific test methods:**
```bash
../../vendor/bin/phpunit --filter="testMethodName" ../modules/custom/module_name/tests/
```

## CI/CD

GitHub Actions runs on every push/PR:
- `unit-tests.yml` — PHPUnit unit tests (PHP 8.5)
- `kernel-tests.yml` — PHPUnit kernel tests (PHP 8.5 + MySQL 8.0)
- `build-install.yml` — Tests site template installation (daily + push/PR)
- `build-artifact.yml` — Builds `dist` branch (on `main` push + every 8 hours)

## Deployment

Source code lives on `main`. Deployment artifacts are built and pushed to `dist`:
```bash
acli push:artifact \
  --destination-git-urls=<ACQUIA_GIT_URL> \
  --destination-git-branch=dist
```

The artifact build process: `composer install --no-dev` → install Drupal → export config → strip dev files → push to `dist`.

## Git Workflow

1. Branch from `main`: `git checkout -b ONR-123/feature-description`
2. Commit with ticket prefix: `ONR-123 | Add countdown banner`
3. Open PR to `main`
4. CI must pass before merge
5. Artifact auto-builds on merge to `main`

## Key Environment Variables

| Variable | Purpose |
|----------|---------|
| `AH_SITE_ENVIRONMENT` | Acquia environment (`prod`, `dev`, `test`) |
| `AH_APPLICATION_UUID` | Acquia Cloud application UUID |
| `SUBSCRIPTION_ID` | Trial subscription identifier |

## Testing Tips for Kernel Tests

Kernel tests that depend on `acquia_id` parameters must override them via a compiler pass (priority `-200`) in the test's `register()` method, since `AcquiaIdServiceProvider::alter()` runs after `register()` and sets staging URLs in non-prod environments. See `OAuth2ControllerTest.php` for the pattern.

## Common Tasks

### Adding a new custom module
1. Create directory under `docroot/modules/custom/`
2. Add `*.info.yml` with `package: Acquia Trials`, `core_version_requirement: ^11`
3. Add `composer.json` with `type: drupal-custom-module`
4. If bundled in trials, add to `recipes/custom/acquia_trials/recipe.yml`

### Adding a JS library
1. Define in `*.libraries.yml` with dependencies (e.g., `core/drupalSettings`)
2. Attach in `hook_page_attachments()` or block `build()` method
3. For external scripts: use `type: external` with `attributes: { async: true }`

### Overriding service parameters per environment
1. Create `src/{ModuleName}ServiceProvider.php` extending `ServiceProviderBase`
2. Implement `alter(ContainerBuilder $container)` to call `$container->setParameter()`
3. Drupal auto-discovers by naming convention — no registration needed
