# Config Language Lock

## Purpose

`config_language_lock` ensures that site configuration is stored in one
explicitly chosen language, regardless of request language, site default
language, or the source language of imported configuration. It also provides a
settings page to update configuration that may have been created earlier in
another language or slipped through (e.g. via config sync, where enforcement is
intentionally skipped).

## Requirements

The module itself only requires Drupal core. The Language and Interface 
Translation modules are optional and change how much the lock can do:

- **Without Language**: the site has exactly one language — the site default —
  and that is the only lock target the settings page offers. Locking is still
  useful, because shipped, recipe-imported and programmatically created
  configuration can carry empty, missing, unknown or foreign langcodes.
- **With Language**: any configured language can be locked, the locked language
  is protected from deletion and annotated in the language overview, and the
  lock can follow the site default language automatically.
- **With Interface Translation**: configuration translations are carried along
  when the lock language changes, and locale's own extension install rewrites
  are either delegated to or taken over, depending on whether a lock is set.

The settings page is accessible with either this module's own `administer
configuration language` permission or core's `administer languages` permission,
which only exists when the Language module is installed.

## Why This Exists

Drupal is perfectly capable of storing configuration in any language and
translating it into others. The problem is that almost no config edit or list
form shows which language a config object is in. Only three core entity types
expose a language selector directly (`menu`, `date_format`,
`taxonomy_vocabulary`), and Views only surfaces one deep in the edit-details
subform. For everything else — content types, image styles, text formats,
roles, and most contrib config — there is no visible indicator and no way to
change the language after creation. This is very different from content, where
language is prominently displayed and navigation by language is built in.

Because the language is invisible, a multilingual site can quietly accumulate
configuration in a mixture of languages due to several gaps in core: UI forms
use the request language, module and theme installs do not reliably rewrite
langcodes to the site default, recipes import config as-is, and config actions
follow the request language. The result is a massive mix of source languages
with no way to see or fix it through the UI. This is confusing when setting up
translations — the source language of each config object determines which
language the translation UI shows as "original" — and becomes a practical
problem when newer tooling such as Canvas relies on strict language consistency
and breaks when it is not present.

## High-Level Behavior

### Opt-in model (no side effects by default)

After initial installation and when `locked_langcode` is unset, the module does
not interfere with Drupal's config handling intentionally. This keeps existing
behavior unchanged until an administrator explicitly selects a configuration
language. This means installing the module without configuring it should be
safe and undoable.

### When lock language is set

When a valid lock language is configured, the module:

- enforces config entity `langcode` on save with `hook_entity_presave`
- hides language selectors on core config entity forms where they would appear (menu, date format, vocabulary and view)
- protects the lock language from deletion
- annotates the lock language and the site default language in language admin UI
- replaces the site default language radio buttons in the language overview with a select list in a **Default language** fieldset below the table
- runs rewrite batches after module/theme installs
- normalizes recipe-imported config to the lock language
- optionally follows the site default language automatically when it changes

## Settings UI

The settings page at `admin/config/regional/config-language-lock` provides:

- current distribution table of config items by language to help debug issues
- optional "follow site default language" checkbox — when enabled, changing the
  site default on the language admin page automatically updates the lock and
  rewrites all config; the language selector is managed automatically
- lock language selector
- optional "no lock" state (`- Do not lock configuration language -`)
- confirmation checkbox for lock language changes

## Language Rewrite and Translation Handling

Batch rewrites are handled by `ConfigLanguageLockBatch` and
`ConfigLanguageLockConfigManager`.

For affected config, the manager:

- updates active config `langcode` to the lock language
- optionally integrates locale translation override data when available
- tracks and reports stats for changed config and translation operations

## Module/theme install and recipe integration

### Module/theme installs

When lock is enabled and install is not config-sync driven, extension install
hooks trigger a rewrite batch to keep imported config aligned to the lock 
language. This happens after install the same way core would handle it in
locale.

However when lock is enabled, `#[RemoveHook]` suppresses locale's install hooks
for `modules_installed` and `themes_installed` to avoid duplicate or conflicting
language rewrite behavior.

### Recipes

A subscriber to `RecipeAppliedEvent` normalizes recipe-shipped config files to
the lock language after recipe apply. Config entities created by config actions
during recipe execution are handled by `hook_entity_presave` instead. Modules
and themes installed by recipes are handled by the extension install hooks above.

## Detailed documentation

Full scenario-by-scenario documentation with comparison tables and flow diagrams
is available in the `docs/` directory:

- [Overview](docs/index.md) — enforcement points, opt-in model, locale interaction
- [Issues addressed](docs/issues.md) — the specific Drupal core behaviors and error cases this module fixes
- [Module install](docs/module-install.md) — how module installs affect config langcodes
- [Theme install](docs/theme-install.md) — same for themes, including the batch-not-processed edge case
- [UI forms](docs/ui-forms.md) — form-based config entity creation and langcode enforcement
- [Config actions](docs/config-actions.md) — programmatic entity creation and its langcode behavior
- [Recipes](docs/recipes.md) — direct config files and extension config installed via recipes
- [Lock switch lifecycle](docs/lock-switch-lifecycle.md) — translation round-trips when the lock language changes
- [Site default lifecycle](docs/site-default-lifecycle.md) — locale's behavior vs the lock across multiple installs
- [Language management](docs/language-management.md) — language deletion protection and stale lock handling
