# Recording applied recipes

## What happens

Core fires `RecipeAppliedEvent` every time a recipe has been applied, once for
the recipe itself and once for every recipe it applied.
`RecipeSubscriber` listens to that event and asks `RecipeTracker` to record
the recipe.

The tracker walks the recipe and every recipe under it. For each one that has a
`composer.json` with a name starting with `drupal/`, it stores one entry in the
`recipe_locale.recipes` config object, keyed by the drupal.org project name:

```yaml
recipes:
  drupal_cms_search:
    package: drupal/drupal_cms_search
    version: 2.x-dev
    label: Search
  haven:
    package: drupal/haven
    version: 1.x-dev
    label: Haven
```

The tracker also stores the translatable values of the configuration each
recipe ships. That is a separate topic, see
[The configuration recipes ship](shipped-config.md).

## Why the whole tree, not just the recipe in the event

A recipe applies its sub-recipes before its own install list. So a module
installed by a recipe misses the events of the recipes applied before it. In
Drupal CMS the Basics recipe applies nine core recipes and the Easy Email
recipes, and only then installs its modules. Recording the whole tree at every
event fixes that: the site template's own event is the last one and includes
everything.

## Why the record lives in configuration

Nothing else keeps this list:

- Core fires the event and does not write anything down.
- Composer knows a recipe only while it is in `composer.lock`. The core unpack
  plugin removes it from there on `composer require`, and the project template
  does not commit the recipe directory. After a deploy from git the recipe is
  gone.
- Project Browser keeps the paths of applied recipes in state, only for recipes
  applied after it was installed. State is not deployable to other environments.

Configuration is exported and deployed with the site. Recipes are usually
applied on a development site. The production site only imports the config,
and the module reacts to that import.

## How the version is decided

Locale builds the download URL from the project name and the version, for
example `drupal_cms_starter-2.x.de.po`. Without a version there is nothing to
download. The tracker looks in this order:

1. **Composer's runtime data**, while the package is still in `composer.lock`.
   This is the same data `composer show` prints.
2. **The `version` key in the recipe's `composer.json`.** Composer forgets an
   unpacked recipe, so this key is the only record left. Drupal CMS writes it
   into its own recipes when it tags a release, and the
   `drupal/site_template_helper` Composer plugin writes it into any recipe it
   installs. Neither drupal.org packaging nor a plain `git` checkout provides
   it.
3. **Nothing.** The recipe stays on the list with an empty version, and Locale
   is not told about it. The list page shows it as "unknown".

The recorded version is the version that was applied. Updating the recipe
package with Composer changes nothing on the site, so the record stays as it
is until the recipe is applied again. Then the new version is recorded.

The `-dev` suffix is stripped when the version is given to Locale. The
translation server serves dev branches without it: `drupal_cms_starter-2.x.de.po`
exists, `drupal_cms_starter-2.x-dev.de.po` does not. The server serves a file
per release when it has one, `gin-5.0.15.de.po` for example. When it has no
file for a version, it redirects to the branch file: `drupal_cms_starter-2.0.0.de.po`
is redirected to `drupal_cms_starter-2.x.de.po` at the time of writing.

The `composer.json` key is a polyfill for something core could do itself when
it unpacks a recipe; the core issue is
https://www.drupal.org/project/drupal/issues/3607162.

## What is skipped

- Core's own recipes have no `composer.json`. Their strings are in core's
  translation files, so they need no project of their own. Their configuration
  still needs a shipped copy, see the shipped config page.
- Recipes from other vendors than `drupal/`. There are no translation files for
  them on localize.drupal.org.

## Locale is optional

Recording does not need the Interface Translation module. A site that adds
Locale later starts with the full list.
