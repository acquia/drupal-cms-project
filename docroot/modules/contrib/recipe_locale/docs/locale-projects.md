# Recipes as translation projects

## What happens

Locale keeps a list of translatable projects, one per installed module and
theme, and uses it to download translation files, check for updates, and
import them. It builds the list from the info files of the installed
extensions. A recipe is not an extension, so it is never on that list.

The only way to add something to the list is
`hook_locale_translation_projects_alter()`. `LocaleHooks` implements it and
adds one project per recorded recipe that has a version:

```php
$projects['haven'] = [
  'name' => 'haven',
  'project_type' => 'recipe',
  'info' => ['name' => 'Haven', 'project' => 'haven', 'version' => '1.x'],
];
```

From then on Locale treats the recipe like a module. It downloads
`haven-1.x.de.po` at the end of a German site install, when German is added to
the site later, when cron checks for updates, and when someone clicks "Check
manually" on the translation status report. A recipe without a file on the
server is a normal "not found" for Locale: it logs a notice and does nothing
else.

## Why the projects come from the deployed list

Locale does not keep its project list permanently. It stores the list in a
key/value store and builds it again whenever it wants: when a module is
installed or uninstalled, when the status report is opened, and "Check
manually" empties the store before building it. Anything the hook does not
supply again on such a rebuild is marked disabled, and Locale keeps disabled
records around.

So the hook always answers from the recorded list in configuration. That list
is the same on every rebuild, and the same on every site that imports the
config, so the recipes Locale knows never depend on what happened on one
particular site. A project Locale cannot rebuild would cause errors: the
status report looks every stored project up by name and logs PHP warnings for
the ones it cannot find.

## Why the project type is `recipe`

Nothing in core branches on the project type. The type makes the records
recognizable, so that uninstalling this module can find and remove them, and
so that a person reading the store knows where they came from. The only
name-keyed logic in Locale is for extension installs and uninstalls; recipe
names do not collide with those.

## When Locale is told about a new recipe

- **On a running site**, after a recipe is applied, the config save triggers
  `LocaleIntegration::update()`. It rebuilds Locale's project list and queues
  a batch that downloads and imports the recipe's translations, the same way
  Locale reacts to a module install. The command-line recipe command runs no
  batch, so there the download waits for cron or for "Check manually".
- **On a site that imports the config**, for example a deployment, the same
  config save event fires and the same batch is queued.
- **During a site install**, the module does nothing itself: the installer
  downloads translations for every project at the end.

## Locale is optional

Recording works without the Interface Translation module. The parts that talk
to Locale, the project list hook's data source, the download step and the
cleanup, are registered by the module's service provider only when Locale is
installed. The two admin pages exist only then, and so do the tabs on the
translation status report, which a deriver defines only when Locale is
installed. A site that installs Locale later gets the recorded recipes on its
project list the first time Locale builds it.

## Removing a recipe from the list

Removing a recipe deletes its translation files, its translation status and
its project record, in that order, and then its config entry and the stored
copy of the config it shipped. These are the same steps Locale takes when a
module is uninstalled. Skipping them would leave the records that cause the
warnings described above. Applying the recipe again puts it back on the list.

## Uninstalling the module

Uninstalling does the Locale part of that for every recorded recipe: files,
status and project records go, for the recipes on the list and for any project
of type `recipe` Locale still has. The recorded list and the stored copies are
this module's configuration, so Drupal removes them with the module.
