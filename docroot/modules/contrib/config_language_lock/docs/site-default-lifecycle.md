# Site default language lifecycle

This page compares how config langcodes evolve across three scenarios:

1. No locale module — site default and module installs have no effect on langcodes
2. Locale enabled, no lock — module installs trigger locale's rewrite batch
3. Lock set — the lock normalizes all config regardless of locale or site default

## Without locale: site default changes have no effect

Without the locale module, there is no hook that rewrites config langcodes on
module install. The site default language is completely irrelevant to config
langcodes. Changing it has no effect on active config, and installing modules
always produces config with whatever langcode is shipped in the YAML (typically
`en`).

### Without locale, no lock

| When | `node.type.lock_lifecycle` | Newly installed step config |
|---|---|---|
| Initial install | `en` | — |
| Change site default to `de` | `en` (unchanged) | — |
| Install step 1 (site default = `de`) | `en` (unchanged) | `en` |
| Change site default to `xx` | `en` (unchanged) | — |
| Install step 2 (site default = `xx`) | `en` (unchanged) | `en` |

### Without locale, lock = xx

Setting a lock normalizes all existing config to the lock language immediately
via the lock switch batch. After that, newly installed modules are also
normalized to the lock language by the lock's own `hook_modules_installed` —
locale is not involved. The site default plays no role.

| When | Lock | `node.type.lock_lifecycle` langcode | Newly installed step config |
|---|---|---|---|
| Before lock set (site default = `de`) | `en` | `en` | — |
| Set lock to `xx` | `xx` | `xx` (rewritten by lock switch) | — |
| Install step 1 | `xx` | `xx` (unchanged) | `xx` |
| Install step 2 | `xx` | `xx` (unchanged) | `xx` |

## Background: what locale does on module install

When locale is enabled and a module is installed via the UI, locale's
`updateDefaultConfigLangcodes()` runs as part of the post-install batch. It:

1. Finds all config objects tracked in locale's install storage (i.e.
   extension-managed config from `config/install/` directories).
2. For each one, if the active `langcode` is exactly `'en'`, rewrites it to the
   current site default language.
3. Immediately afterwards, `updateConfigTranslations()` merges any available
   translation for the new langcode into active config.

Two important constraints:
- Only `en` is rewritten. If a config object was already rewritten to `xx` in
  a previous install, it is left alone even if the site default changes later.
- Only extension-managed config is processed. Config created via the UI is not
  in locale's install storage and is never touched by this batch.

## Test setup

The test uses a `node.type.lock_lifecycle` entity (extension-managed, shipped
as `en`) and a `node.type.lock_lifecycle_ui` entity (created via the UI, not
tracked by locale). Three step modules are installed one by one as the site
default changes.

Translations for xx ("XX content type name") and yy ("YY content type name")
are imported via `.po` files before any step begins.

## Without a lock: site default lifecycle across three steps

### Initial state (site default = en)

The content type is installed with `langcode: en`. A UI-created type also gets
`en`. Both xx and yy overrides are in place from the `.po` import.

| Config | Langcode | Name | xx override | yy override |
|---|---|---|---|---|
| `node.type.lock_lifecycle` | `en` | "Lock lifecycle" | "XX…" | "YY…" |
| `node.type.lock_lifecycle_ui` | `en` | "Lock lifecycle UI" | — | — |

### Step 1: change site default to xx (no module install yet)

Changing the site default language does **not** touch active config. Langcodes
and names are unchanged.

| Config | Langcode | Name | xx override | yy override |
|---|---|---|---|---|
| `node.type.lock_lifecycle` | `en` | "Lock lifecycle" | "XX…" | "YY…" |
| `node.type.lock_lifecycle_ui` | `en` | "Lock lifecycle UI" | — | — |

### Step 1: install a module while site default = xx

`updateDefaultConfigLangcodes()` finds `lock_lifecycle` with `langcode: en`
and rewrites it to `xx`. Then `updateConfigTranslations()` merges the xx
translation into active config. The xx override is **kept** (redundantly
containing the same value). The UI-created type is not extension-managed — stays `en`.

Newly installed step 1 config gets `xx` langcode.

| Config | Langcode | Name | xx override | yy override |
|---|---|---|---|---|
| `node.type.lock_lifecycle` | `xx` | "XX content type name" | "XX…" ⚠ | "YY…" |
| `node.type.lock_lifecycle_ui` | `en` ⚠ | "Lock lifecycle UI" | — | — |
| `node.type.lock_lifecycle_step1` | `xx` | — | — | — |

⚠ xx override is kept even though active config already contains that value —
locale does not clean up the redundant override after merging it in.
⚠ UI-created config is not tracked in locale's install storage so it is never
rewritten by `updateDefaultConfigLangcodes()`, even though its langcode is `en`
and the site default is now `xx`.

### Step 2: change site default to yy (no module install)

No change to active config.

### Step 2: install a module while site default = yy

`updateDefaultConfigLangcodes()` looks for config with `langcode: en`.
`lock_lifecycle` now has `langcode: xx` — not `en` — so it is **not** rewritten.
Only config that is still at `en` would be rewritten. Newly installed step 2
config gets `yy`.

The UI-created type has `langcode: en` but is not extension-managed, so it is
not rewritten either.

| Config | Langcode | Name | xx override | yy override |
|---|---|---|---|---|
| `node.type.lock_lifecycle` | `xx` | "XX content type name" | "XX…" ⚠ | "YY…" |
| `node.type.lock_lifecycle_ui` | `en` ⚠ | "Lock lifecycle UI" | — | — |
| `node.type.lock_lifecycle_step2` | `yy` | — | — | — |

### Step 3: change site default to en, then install a module

`updateDefaultConfigLangcodes()` skips the rewrite entirely when the site
default is `en` (it only rewrites when the default is not English). Newly
installed step 3 config stays `en`.

| Config | Langcode | Name | xx override | yy override |
|---|---|---|---|---|
| `node.type.lock_lifecycle` | `xx` | "XX content type name" | "XX…" ⚠ | "YY…" |
| `node.type.lock_lifecycle_ui` | `en` ⚠ | "Lock lifecycle UI" | — | — |
| `node.type.lock_lifecycle_step3` | `en` | — | — | — |

## With a lock: site default changes are irrelevant by default

Once a lock language (xx) is set, this module removes locale's install hooks
and manages all config rewrites itself. By default, site default changes have no
effect on config langcodes — the lock is a fixed value independent of the site
default.

If the **follow site default language** option is enabled on the settings page,
changing the site default on the language overview form automatically updates
`locked_langcode` and runs the rewrite batch. All config is then normalized to
the new site default immediately — without any additional manual step. See
[Follow site default language](index.md#follow-site-default-language) for
details.

Without the follow option, site default changes have absolutely no effect on the
lock. Everything is consistently `xx` throughout.

### Initial state (lock = xx set, site default = en)

Setting the lock triggers a batch that rewrites all existing config to `xx`.

| Config | Langcode | Name |
|---|---|---|
| `node.type.lock_lifecycle` | `xx` | "XX content type name" |

### After any site default change

No change. The lock ignores the site default completely.

### After any module install (lock = xx, any site default)

The lock batch rewrites newly installed config to `xx`. The site default is
irrelevant.

| Config | Langcode | Name |
|---|---|---|
| `node.type.lock_lifecycle` | `xx` | "XX content type name" |
| `node.type.lock_lifecycle_ui` | `xx` | "Lock lifecycle UI" |
| `node.type.lock_lifecycle_step1` | `xx` | — |
| `node.type.lock_lifecycle_step2` | `xx` | — |
| `node.type.lock_lifecycle_step3` | `xx` | — |

## Comparison summary

| Scenario | `node.type.lock_lifecycle` langcode | `node.type.lock_lifecycle_ui` langcode | Step 1 config | Step 2 config | Step 3 config |
|---|---|---|---|---|---|
| No locale, no lock (any site defaults) | `en` (never touched) | `en` | `en` | `en` | — |
| No locale, lock = xx | `xx` (lock switch + all installs) | `xx` | `xx` | `xx` | — |
| Locale, no lock, site defaults EN→XX→YY→EN | `xx` ⚠ (frozen after step 1 install) | `en` ⚠ (never rewritten) | `xx` | `yy` ⚠ | `en` ⚠ |
| Locale, lock = xx | `xx` (consistent throughout) | `xx` | `xx` | `xx` | `xx` |

⚠ Without a lock, locale's rewrite is incomplete: `lock_lifecycle` is frozen at
the langcode from the first install and never updated as the site default changes;
`lock_lifecycle_ui` is never rewritten because it is not extension-managed; and
step 2 and step 3 config arrive in different languages from step 1, leaving the
site with inconsistent config langcodes across installs.

## Key differences between approaches

| Behavior | No locale, no lock | Locale, no lock | Lock |
|---|---|---|---|
| Changing site default rewrites active config | No — nothing does | No — only module install does | No — lock switch batch does |
| Module install rewrites existing config langcodes | No — locale not present | Yes — only `en`, only extension-managed | Yes — all langcodes, all config |
| Config already at a non-en langcode is rewritten again | No | No — only `en` is rewritten | Yes — lock batch rewrites all langcodes |
| UI-created config is rewritten | No | No — not in locale's install storage | Yes — lock applies to all active config |
| Newly installed config langcode | Shipped langcode (`en`) | Site default at time of install | Lock language always |
| Non-`en` invalid langcodes rewritten | No | No | Yes |
| Consistent langcode across all config | No | No — depends on install order and site default history | Yes |

## Related pages

- [Lock switch lifecycle](lock-switch-lifecycle.md) — the four-step rewrite process when the lock language changes
- [Module install](module-install.md) — per-install langcode outcome table
