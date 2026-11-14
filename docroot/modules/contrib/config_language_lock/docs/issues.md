# Issues this module addresses

This module was written to fix a class of problems that cause config langcodes
to drift or end up in unexpected states. Some problems are silent (config stored
in the wrong language with no error), some are errors (500s with bad data left
in the database), and some are gaps in core's own correction mechanisms.

## One core issue to rule them all

The root cause of most problems documented here is tracked in core issue
[#3337864 — Introduce a dedicated "Configuration default language" different from "Site default language"](https://www.drupal.org/project/drupal/issues/3337864).

That issue proposes a first-class concept of a configuration language in core
so that config always has a well-defined, explicitly chosen source language
independent of the request language and site default. This module is a
contrib-level workaround until that issue lands and people update to Drupal versions with that built-in.

## Config langcode drift

### UI forms use the request language

When an admin visits the Drupal UI under a non-default URL prefix
(`/xx/admin/…`), config entities created through forms are saved with
`langcode: xx` — whatever language the page was served in. There is no warning
and no selector to override it for most entity types. On a multilingual site
this silently accumulates config in whichever language each admin happened to
use at the time.

See [UI forms](ui-forms.md).

### Config actions use the request language when no langcode is given

Config actions (`createOrUpdate` etc.) that do not specify an explicit langcode
default to the current request language. On a site where the request language
varies (URL prefix negotiation, programmatic context switches), this means the
same recipe or deployment script can produce different langcodes depending on
the environment it runs in.

See [Config actions](config-actions.md).

### Site default language has no effect on config without locale

Without the locale module, changing the site default language never rewrites
active config langcodes and has no effect on newly installed config either. Yet
UI forms and config actions still follow the current request language. The
result is that the site default and the config langcodes can silently diverge:
an admin sets the site default to `de` expecting config to follow, but config
created through the UI or installed from modules stays in whatever language it
happened to be saved in.

See [Site default lifecycle](site-default-lifecycle.md).

### Changing the site default with locale does nothing on its own

With locale enabled, changing the site default language does not immediately
rewrite any active config. The rewrite only happens as a side effect of
installing an extension — `updateDefaultConfigLangcodes()` runs as part of the
post-install batch. If no extension is installed after the site default changes,
config langcodes are completely unaffected.

This module's [follow site default language](index.md#follow-site-default-language)
option addresses this by hooking into the site default change form and running
the full rewrite batch automatically whenever the site default is changed.

### Locale's site default rewrite only fires once per config object

Even when an extension install does trigger `updateDefaultConfigLangcodes()`,
it only rewrites config whose resolved langcode is `'en'` (locale's
`getDefaultConfigLangcode()` normalizes empty and missing to `'en'`, so those
are also rewritten). Once a config object has been rewritten to any other
non-`en` value — say `xx` after a first site default change — it is never
touched again. Subsequent site default changes have no effect on it. Meanwhile
newly installed modules always arrive in the current site default language, so
after a second site default change the site ends up with config in a mix of
languages: old config frozen at the first non-`en` value, new config in the
latest site default.

With this module's [follow site default language](index.md#follow-site-default-language)
option enabled, all config is always rewritten to the current site default on
every change — not just `en` config on first install. There is no "frozen at
first non-`en` value" problem.

See [Module install](module-install.md), [Site default lifecycle](site-default-lifecycle.md).

### Locale never rewrites UI-created config

`updateDefaultConfigLangcodes()` only processes config that is tracked in
locale's install storage — that is, config shipped in a module or theme's
`config/install/` directory. Config entities created through the UI are not
in that storage and are permanently invisible to locale's rewrite batch,
regardless of their current langcode or the site default. An admin who creates
a content type while the site default is `en` and later changes the site default
to `de` will find that content type's langcode stays `en` forever — locale
will never correct it.

See [Site default lifecycle](site-default-lifecycle.md), [Module install](module-install.md).

### Locale skips config with no translatable elements at install time

Locale's `updateDefaultConfigLangcodes()` only rewrites config objects that
have at least one translatable element at the moment of install. Config entities
that ship with no translatable strings — for example, a text editor format whose
plugins are all added later — are left with langcode `en` even on a non-English
site. Once a plugin or third-party setting that is translatable is added to such
a config, the translation UI encounters an unrecognized langcode (if `en` is not
an installed language on that site) and throws exceptions. See
[drupal.org/project/drupal/issues/3600904](https://www.drupal.org/project/drupal/issues/3600904).

Test coverage for this bug scenario:
- `ConfigLanguageLockExtensionInstallTest::testExtensionInstallKeepsOriginalLangcodesWhenUnlocked()`
  shows that without locale, config stays with shipped 'en' langcode
- `ConfigLanguageLockExtensionInstallTest::testExtensionInstallRewritesEnLangcodeToSiteDefaultWhenUnlockedWithLocale()`
  demonstrates the bug: 'en' config with no translatable elements stays 'en'
  even when site default is 'de'
- `ConfigLanguageLockExtensionInstallTest::testExtensionInstallImportUsesLockedLanguage()`
  verifies the fix: this module's lock overwrites all config including those
  with no translatable elements

UI-created config translation swapping is tested in the lifecycle tests:
- `ConfigLanguageLockLifecycleWithLockTest::testLockedLanguageSwitchLifecycle()`
  and `testLockedLanguageSwitchLifecycleWithTranslateEnglish()` include UI-created
  config with language overrides (translations), verifying that the schema-based
  extraction fallback swaps translations correctly when locale is not available

### Theme installs skip locale's batch entirely on the standard path

The standard theme install UI controller returns a redirect before calling
`batch_process()`, so locale's rewrite batch is queued but never executed. All
langcodes stay exactly as shipped — even `en` is not rewritten to the site
default. Only the experimental theme confirm form runs the batch. See
[drupal.org/project/drupal/issues/3612163](https://www.drupal.org/project/drupal/issues/3612163).

See [Theme install](theme-install.md).

### Recipe `config/` files are imported as-is

`RecipeConfigInstaller` saves config files verbatim; whatever langcode is in
the YAML is what ends up in the database. A recipe author controls only the
langcodes they chose when writing the files — there is no mechanism for the
installing site to say "use our language". See
[drupal.org/project/drupal/issues/3472317](https://www.drupal.org/project/drupal/issues/3472317).

See [Recipes](recipes.md).

## Bad langcode values stored without correction

### Empty langcode is always kept in storage

A config action or recipe `config/` file that contains an empty `langcode`
value will have that empty value stored in the database. What happens next
depends on the entity type: if it carries the `FullyValidatable` schema marker,
`ConfigActionManager` / `RecipeConfigInstaller` runs validation after saving and
aborts with a 500 — leaving the entity with the empty langcode in the database.
If the entity type has no `FullyValidatable` marker, validation never runs and
the empty langcode is silently accepted with a 200. In both cases the invalid
value ends up persisted with no automatic correction.

See [Config actions](config-actions.md) and [Recipes — Direct config files](recipes.md).

### Unknown langcode is always kept in storage

The same applies to an unknown langcode (e.g. `zz` — a code that is not an
installed language): it is saved to the database as-is. `FullyValidatable`
entity types then abort with a 500 after the save; non-`FullyValidatable` types
accept the value silently. Module and theme installs exhibit the same behavior
— the standard installer never runs `FullyValidatable` validation, and locale
only rewrites config whose resolved langcode is `'en'`, so unknown langcodes
from shipped config are never corrected. Note that an empty langcode is
different: locale's `getDefaultConfigLangcode()` normalizes empty to `'en'`
and then rewrites it to the site default on module install.

See [Config actions](config-actions.md), [Recipes — Direct config files](recipes.md),
[Module install](module-install.md), [Theme install](theme-install.md).

## Translatable field extraction: Only locale provides the map structure

Drupal's config translation infrastructure needs to know which config fields are
translatable and to build a nested structure (the "translatable map") for moving
data between storage locations. The typed config schema tells us which fields
*are* translatable via the `translatable` marker, but extracting them into a
structured map is only implemented in locale module's
`LocaleConfigManager::getTranslatableData()`.

Meanwhile, config_translation module uses typed config schema to:
- Check if config has any translatable fields (`hasTranslatable()`)
- Build form elements for translatable fields
- Read and write translation data via language config overrides

However, when this module needs to **move translatable data** between config
storage locations (swapping translations when lock language changes), there is no
public API that returns the translatable-map structure. This module works around
this by:

- Copying locale's `getTranslatableData()` algorithm with attribution
  (see `getTranslatableDataFromTypedConfig()`)
- Walking the typed config schema to extract the same nested structure
- Supporting both shipped config (via locale's default storage) and UI-created
  config (via schema-based extraction)

## What this module does to fix these issues

When a lock language is configured:

- **`hook_entity_presave`** overwrites every config entity's `langcode` to the
  lock language at save time, before any `FullyValidatable` validation runs.
  This eliminates 500 errors from bad langcodes and removes request-language
  drift from UI forms and config actions.

- **`hook_modules_installed` / `hook_themes_installed`** queue a rewrite batch
  that calls `updateConfigForLockedLanguageSwitch()` on all installed config,
  overwriting every `langcode` key regardless of its current value. Locale's
  own hooks are removed to prevent conflicts. This covers the locale
  partial-rewrite gap, the theme-batch-not-processed gap, and the
  no-translatable-elements gap
  ([#3600904](https://www.drupal.org/project/drupal/issues/3600904)) — config
  that locale would skip because it has no translatable strings at install time
  is still normalized to the lock language.

- **`RecipeAppliedEvent` subscriber** post-normalizes the recipe's own `config/`
  files after the recipe completes, covering the as-is import gap.

- **Language deletion protection** prevents the locked language from being
  deleted via the UI, avoiding an instant stale-lock situation.

- **Stale lock guard** in `getLockedLangcode()` returns `NULL` if the stored
  langcode no longer corresponds to a known language, preventing any rewrite to
  a nonexistent langcode.

See [index](index.md) for the full enforcement-point summary.
