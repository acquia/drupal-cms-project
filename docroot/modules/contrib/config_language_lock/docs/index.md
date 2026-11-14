# Config Language Lock

`config_language_lock` ensures that site configuration is stored in one
explicitly chosen language regardless of request language, site default language,
or the source language of imported configuration.

## Why this exists

Drupal is perfectly capable of storing configuration in any language and
translating it into others. The problem is that almost no config edit or list
form shows which language a config object is in. Only three core entity types
expose a language selector directly (`menu`, `date_format`, `taxonomy_vocabulary`),
and Views only surfaces one if you navigate into the edit-details subform. For
everything else — content types, image styles, text formats, roles, and most
contrib config — there is no visible indicator and no way to change the language
after creation. This is very different from content, where language is
prominently displayed and navigation by language is built in.

Because the language is invisible, a multilingual site can quietly accumulate
configuration objects in a mixture of languages due to several gaps in core:

- UI forms save with whatever language the current request is in.
- Module and theme installs ship config in English (`en`); the locale module
  rewrites `en` to the site default — but only for extension-managed config,
  only when the site default is not English, and only by processing a batch
  that some install paths never run.
- Recipes import config files as-is and may contain arbitrary or invalid
  langcodes.
- Config actions create entities using the current request language.

The result is configuration in a massive mix of languages with no way to see or
fix it through the UI. This is confusing when setting up translations — the
source language of each config object determines which language the translation
UI shows as "original" — and becomes a practical problem when newer tooling
such as Canvas relies on strict language consistency and breaks when it is not
present.

This module provides a **lock language** — a single explicitly configured
langcode — and enforces it consistently at every write path.

## Opt-in model

Installing the module without configuring it has no side effects. The lock is
active only when an administrator explicitly selects a language on the settings
page at `admin/config/regional/config-language-lock`. Until then, Drupal's
default behavior is preserved unchanged.

## Language and Interface Translation module optional

The module depends on Drupal core only. The Language module and Interface 
Translation modules are optional:

| Modules installed | What the lock can do |
|---|---|
| Neither | The site default is the only selectable lock language. All enforcement points still run, so shipped, recipe-imported and programmatically created config is normalized to that one langcode. |
| Language | Any configured language can be locked, the locked language is protected from deletion and annotated in the language overview, and follow-site-default becomes meaningful. |
| Language + Interface Translation | Config translations are carried along when the lock language changes, and locale's extension install rewrites are delegated to or taken over as described below. |

## Enforcement points

When a lock language is configured the module enforces it at five points:

| Point | Mechanism |
|---|---|
| Any config entity save | `hook_entity_presave` overwrites `langcode` to the lock language |
| Module install | `hook_modules_installed` queues a rewrite batch; locale's own hook is removed |
| Theme install | `hook_themes_installed` queues a rewrite batch; locale's own hook is removed |
| Recipe apply | `RecipeAppliedEvent` subscriber post-normalizes the recipe's own `config/` files; config actions and module installs inside recipes are covered by `hook_entity_presave` |
| Settings form submit | A batch rewrites all existing active configuration |
| Site default change _(optional)_ | When follow-site-default is enabled, `hook_form_language_admin_overview_form_alter` appends a submit handler that updates `locked_langcode` and queues the rewrite batch |

## Settings UI

`admin/config/regional/config-language-lock` provides:

- A distribution table showing how many config items are currently in each
  langcode — useful for diagnosing drift.
- A **follow site default language** checkbox — when enabled, the lock language
  updates automatically whenever the site default is changed on the language
  admin overview page. The language selector below becomes read-only while this
  is checked.
- A lock language selector.
- A "do not lock" option (`- Do not lock configuration language -`).
- A confirmation checkbox required before the lock language can be changed,
  because changing it triggers a batch rewrite of all active configuration.

## Follow site default language

The **follow site default language** option on the settings page makes the lock
track the site default automatically. When it is enabled:

- Changing the site default on `admin/config/regional/language` triggers a
  rewrite batch — the same batch used when the lock is changed manually on the
  settings page — and updates `locked_langcode` to match the new site default.
- The language selector on the settings page is disabled while follow is enabled
  (the site default controls it instead).
- The confirmation checkbox is still required when enabling this option, because
  enabling it immediately rewrites all active config to the current site default.
- The language overview page marks the lock language, so administrators can
  see which language controls config writes at a glance. With follow enabled
  the lock language is always the site default language, so it is marked
  "(Site default and configuration language)".

This option is disabled by default. Without it, the lock language is a static
value that changes only when an administrator manually updates it on the settings
page. See [Site default lifecycle](site-default-lifecycle.md) for a comparison.

## Locale interaction

When locale is enabled and no lock is set, locale's own extension install hooks
run as normal. When a lock is set, this module removes locale's
`modules_installed` and `themes_installed` hooks with `#[RemoveHook]` and
delegates back to locale only when no lock language is configured. This prevents
duplicate or conflicting rewrite behavior.

## Detailed documentation pages

- [Issues this module addresses](issues.md) — the specific Drupal core behaviors and error cases this module was written to fix
- [Module install](module-install.md) — how module installs affect config langcodes with and without a lock
- [Theme install](theme-install.md) — same for themes, including the batch-not-processed edge case
- [UI forms](ui-forms.md) — how form submissions behave with and without a lock
- [Config actions](config-actions.md) — programmatic entity creation and its langcode behavior
- [Recipes](recipes.md) — direct config files and extension config installed via recipes
- [Lock switch lifecycle](lock-switch-lifecycle.md) — what happens when the lock language is changed, including locale translation round-trips
- [Site default lifecycle](site-default-lifecycle.md) — how the site default language interacts with config langcodes compared to the lock
- [Language management](language-management.md) — language deletion protection and stale lock handling
