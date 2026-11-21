# The configuration recipes ship

## Downloading the file is not enough

Locale does not translate configuration by looking at what a site has. It
compares the site's configuration with the original copy that was shipped,
takes the translatable values of that original as source strings, looks up
their translations, and writes the translations into the site's config.

It finds the original in the `config/install` and `config/optional`
directories of the installed modules, themes and the profile
(`LocaleDefaultConfigStorage`). Config entities must also carry the
`_core.default_config_hash` key, which the config installer adds to everything
it creates. Config installed by a recipe has that hash. What it lacks is an
original that Locale can find: the recipe's `config` directory is not something
Locale can search especially months after the recipe was applied.

The result on a German Drupal CMS site with Haven: the German file for Haven is
downloaded and imported, "Blog post" is translated to "Blogbeitrag" in the
database, and the content type is still called "Blog post". Core's own recipes
have the same problem: their config lives in `core/recipes`, which Locale does
not search either, so the content editor role stays "Content editor" even
though core's translations know the word.

## Why the recipe directory cannot be used

Pointing Locale at the recipe directory only works while the directory
exists. The core unpack plugin removes a required recipe from
Composer's data, and the project template does not commit the recipe
directory, so a deploy from git does not have it. A month later someone adds
French to the site, Locale runs its checks, and there is nothing to compare
with. The original has to be kept somewhere that deploys with the site.

## What is stored

When a recipe is applied, the tracker reads the config files in the recipe's
own `config` directory and keeps, for each one, only the values whose schema
marks them translatable. They go into one config object per recipe,
`recipe_locale.shipped.<key>`, where the key is the project name, or `core-`
and the directory name for core's recipes:

```yaml
# recipe_locale.shipped.haven
recipe: Haven
config:
  -
    name: node.type.project
    data:
      name: Project
      description: 'A project the organization works on.'
  -
    name: views.view.projects
    data:
      label: Projects
      display:
        default:
          display_options:
            title: Projects
```

Three reasons lead to this format:

- **A list, not a map keyed by config name.** Configuration keys cannot contain
  dots, and config names do.
- **Only the translatable values, not the whole file.** Locale reads nothing
  else from an original. On a Haven site the translatable values of all recipes
  come to about 73 KB; the full files come to about 715 KB. That difference
  would be in every config export and would be loaded on every Locale pass.
- **Config that a module also ships is left out.** Locale finds the original of
  that in the module. That includes config a recipe imports from a module with
  `config: import`: it is installed from the module's files. Config a recipe
  creates with config actions has no shipped file, and content a recipe ships
  is outside Locale altogether.

Config actions and the input prompts of a recipe are planned to be translatable
in https://www.drupal.org/project/drupal/issues/3313863. There, recipe authors
would mark translatable text in `recipe.yml` with a `!translate` tag, and core
would translate it when the recipe is applied. This module cannot do that part
itself, because it gets to the recipe data too late: core reads, checks and
applies `recipe.yml` before this module receives the recipe, and a
`recipe.yml` with that tag does not pass core's checks as of this writing. Until
core supports the tag, do not use it in recipes. Once it does, this module can
add the tagged text of config actions to the stored copies, so those values get
translated into every language of the site as well.

**Only config shipped in English is stored**, with `langcode: en` or no
langcode at all. Locale only translates from English sources, so it could do
nothing with a German original. The copy given to Locale is marked English.

Applying a recipe again replaces its stored copy with what the recipe ships
now, the same way it records the new version.

The kernel test `ImportedModuleConfigTest` checks the first point: config a
recipe imports from a module is translatable with or without the stored copy,
and config the recipe ships itself is translatable only with it.

## When two recipes ship the same object

Core creates a config object only when the site does not have it yet. When a
recipe ships an object that already exists, the recipe's file is skipped, or
in strict mode it has to match what is there. So when two applied recipes ship
the same object, the text in the site's config comes from the recipe applied
first, and that is the copy Locale needs. The module keeps one copy per config
name, under the recipe that stored it first. A recipe applied later does not
replace it.

When the site deletes or renames the object, the copy is removed. What the
recipe shipped is no longer there, and a recipe applied afterwards may create
the object again from its own file. That recipe then keeps the new copy.

Removing a recipe from the list removes its copies, also for objects another
recipe shipped too.

## How Locale gets the copy

The service provider swaps Locale's default config storage class for
`RecipeDefaultConfigStorage`. Its `read()` looks in the extension directories
first, as before, and then in the stored copies. Its `listAll()` adds the
stored config names, which matters at the end of a site install and on a full
refresh: Locale translates "all shipped config" by asking this storage for the
list.

The copy given to Locale is not the stored subset as is. Locale builds typed
data from the copy, and some config schemas need to see keys next to the
translatable ones. Canvas's component input schema needs `component_id` next to
the inputs. Views needs plugin IDs, etc. The stored subset has no such keys.
Giving Locale only the subset would not work.

So `RecipeTracker::readShippedConfig()` builds the copy the way the Language
module lays a translation override over the base config at runtime: it starts
from the site's current config, which has every key any schema class expects,
walks its typed data for the translatable slots, and fills each slot with the stored shipped value, or with
an empty string when the recipe shipped nothing there. The copy is marked
English, because Locale reads the langcode to decide the source language.

The kernel test `ShippedConfigCopyTest` checks these rules:

- A value the site changed does not replace the shipped value.
- Text the site put into a slot the recipe shipped empty stays out of the copy.
  Locale skips empty strings, so that text never becomes a source string.
- A stored value at a path the site no longer has is left out. Only the paths
  of the site's current config are visited, so nothing is added where the site
  has no place for it. The copy has exactly the site's keys.
- A stored value of the wrong shape becomes an empty slot.
- Config the site deleted has no copy at all.

One limit comes from Locale itself, not from this module: it matches by path. In a
list keyed by identifiers, like a Canvas component tree keyed by UUID, that is
stable. In an indexed list, reordering items on the site can pair a stored
string with a different item. A real `config/install` directory behaves the
same way.

## How do the strings get to Locale's database

Locale keeps the source strings of translatable config in its own tables,
together with the config names they appear in, and translates a config object
again whenever one of its strings is imported. Those records are made when
Locale translates the config for the first time.

For config shipped by a module, Locale does that itself: when the module is
installed on a site that has a language other than English, and every time the
config is saved. For config shipped by a recipe, the save happens before this
module has recorded the recipe, so Locale skips it at that moment. The records
are made afterwards:

- on a running site, by the batch step this module queues right after the
  recipe is recorded;
- at the end of a site install, by core's batch over all shipped config, which
  asks this module's storage for the list;
- when a language is added, by Locale's batch over all shipped config, which
  asks the same list.

## How do translations get to the active config and overrides

- **At the end of a non-English site install.** Core's install step translates
  all shipped config in one batch, and the stored copies are part of that list.
  The Drupal CMS installer replaces that step with Config Language Lock's
  batch, which only moves existing translations between languages; it needs
  core's batch as well for any config, module or recipe, to be translated at
  install time.
- **After a recipe's translation file is imported** on a running site.
  `LocaleIntegration::update()` queues a batch operation that runs Locale's
  translation for the config that recipe shipped. That also registers the
  strings with Locale, so every later import refreshes them on its own.
- **When a language is added**, by Locale itself.
