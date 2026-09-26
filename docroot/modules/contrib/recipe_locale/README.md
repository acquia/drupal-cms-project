# Recipe Interface Translation Integration

Keeps a list of the recipes applied to a site, and a copy of the translatable
configuration they shipped. With these, the Interface Translation (Locale)
module can download and update the recipes' translations from
localize.drupal.org and translate their configuration, the same way it does
for modules and themes.

## Why

Locale builds its list of translatable projects from the installed modules and
themes. Recipes are not extensions, so Locale does not know about them. Their
translations exist on localize.drupal.org, but nothing downloads them. After a
recipe is applied, its configuration stays in English (or whatever language it
shipped in) on a site set up in another language.

## How it works

- When a recipe is applied, the module records it, and every recipe it
  applied, in configuration: the drupal.org project name, the Composer package
  name, the version and the recipe's name.
- It also keeps a copy of the translatable values of the configuration each
  recipe shipped, so Locale has an original to compare with after the recipe
  directory is gone.
- It adds the recorded recipes to Locale's project list. From then on Locale
  treats them like any other project: it downloads their translations when
  they are added, when a language is added to the site, and when cron checks
  for updates, and it translates the configuration they shipped.
- Both records are configuration, so they deploy with the site. The Interface
  Translation module is optional: a site that adds it later starts with the
  full list.

## Documentation

The `docs` directory explains each part, why it is needed, and what it stores:

- [Why track recipes at all](docs/why.md)
- [Recording applied recipes](docs/recording-recipes.md), including how the
  version is decided
- [Recipes as translation projects](docs/locale-projects.md)
- [The configuration recipes ship](docs/shipped-config.md)

## Removing a recipe from the list

Go to *Reports > Available translation updates > Recipe translation updates*
and use *Remove*. This deletes the translation files and status that Locale
stored for the recipe, and the stored copy of the translatable configuration
the recipe shipped. The recipe itself stays applied. Applying it again puts it
back on the list.
