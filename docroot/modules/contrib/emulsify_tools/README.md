# Emulsify Tools module

This module provides Twig helpers used in the [Emulsify Design System](https://github.com/emulsify-ds/) and the Drush child theme generation workflow for Emulsify Drupal 6.x.

## Compatibility

Emulsify Tools 1.x is intended for the Emulsify Drupal 6.x child theme generation workflow. The paired Emulsify Drupal 6.x release line supports Drupal `^10.3 || ^11`.

## Usage

### Child theme generation

Emulsify Tools 1.x provides the supported Drush workflow for generating Emulsify Drupal 6.x child themes. Use either command form:

```
drush emulsify my_theme
drush emulsify_tools:bake my_theme
```

The commands are equivalent. The generated child theme uses `emulsify` as its runtime parent theme and should be created under the Drupal custom theme path expected by the command, such as `web/themes/custom/my_theme` in a Composer-based Drupal project.

Drupal core Starterkit-based generation is being prepared for the Emulsify Drupal 7.x release line. For Emulsify Drupal 6.x, use Emulsify Tools for child theme generation.

### BEM Twig Extension

The `bem()` Twig function builds BEM class names and returns them in a form that can be printed into Drupal template attributes.

#### Simple block name (required argument):

`<h1 {{ bem('title') }}>`

This creates:

`<h1 class="title">`

#### Block with modifiers (optional array allowing multiple modifiers):

`<h1 {{ bem('title', ['small', 'red']) }}>`

This creates:

`<h1 class="title title--small title--red">`

#### Element with modifiers and blockname (optional):

`<h1 {{ bem('title', ['small', 'red'], 'card') }}>`

This creates:

`<h1 class="card__title card__title--small card__title--red">`

#### Element with blockname, but no modifiers (optional):

`<h1 {{ bem('title', '', 'card') }}>`

This creates:

`<h1 class="card__title">`

#### Element with modifiers, blockname and extra classes (optional - in case you need non-BEM classes):

`<h1 {{ bem('title', ['small', 'red'], 'card', ['js-click', 'something-else']) }}>`

This creates:

`<h1 class="card__title card__title--small card__title--red js-click something-else">`

#### Element with extra classes only (optional):

`<h1 {{ bem('title', '', '', ['js-click']) }}>`

This creates:

`<h1 class="title js-click">`

### Add Attributes Twig Extension

The `add_attributes()` Twig function merges additional attributes with Drupal's template-level attributes and prevents those attributes from trickling into child includes.

```
{% set additional_attributes = {
  "class": ["foo", "bar"],
  "baz": ["foobar", "goobar"],
  "foobaz": "goobaz",
} %}

<div {{ add_attributes(additional_attributes) }}></div>
```

Can also be used with the BEM Function:

```
{% set additional_attributes = {
  "class": bem("foo", ["bar", "baz"], "foobar"),
} %}

<div {{ add_attributes(additional_attributes) }}></div>
```

## Development

---

### Requires

- [Node.js v12+](http://nodejs.org/)
- [Yarn Package Manager](https://yarnpkg.com/)
- [Commitizen](https://github.com/commitizen/cz-cli) for commit standardization, included in install

### Initial Setup

1. Run `npm install` to install dependencies. You're done!

### Generation Smoke Test

To validate the Emulsify Drupal 6.x child theme generation workflow against this checkout, run:

```
.github/scripts/generation-smoke.sh
```

The script creates a disposable Drupal fixture site, installs Emulsify Drupal `^6`, installs this checkout as Emulsify Tools `1.x`, verifies both Drush command help targets, runs `drush emulsify watson`, validates the generated theme files, and enables the generated child theme. It intentionally does not test Drupal core Starterkit generation.

Requirements: Composer and PHP. The default SQLite fixture database also requires `pdo_sqlite`.

Optional environment variables:

```
FIXTURE_DIR=/tmp/emulsify-tools-generation-smoke
DRUPAL_VERSION=11.3.*
EMULSIFY_VERSION=^6
TOOLS_VERSION=1.0.99
DRUSH_VERSION=^13
THEME_NAME=watson
DB_URL=sqlite://sites/default/files/.ht.sqlite
KEEP_FIXTURE=1
```

### Committing Changes

We utilize the [Conventional Changelog](https://github.com/conventional-changelog/conventional-changelog) standard through Commitizen. Follow these steps when committing your work to ensure useful release notes.

1. Stage your changes, ensuring they encompass exactly what you wish to change, no more.
2. Run `yarn commit` and follow the prompts to craft the perfect commit message.
3. _Rejoice!_ For now your commit message will be used to create the changelog for the next version that includes that commit.

## Release

---

Emulsify Tools 1.x releases are published manually. This keeps GitHub and Drupal.org publication as separate, deliberate actions.

1. Merge the release-ready changes into `1.1.x` and confirm the Generation Smoke workflow passes.
2. Create and push a numeric release tag, such as `1.1.1`, to GitHub.
3. Create the matching GitHub release.
4. Push the `1.1.x` branch and release tag to Drupal.org.
5. Create the release on Drupal.org and set it as the supported, recommended release.

### Creating a release on GitHub

- Fetch the latest `1.1.x` branch and create an annotated numeric tag from it.
- Push only that tag to GitHub.
- Create a GitHub release from the tag and include the relevant release notes.
- Verify the release on the [GitHub Releases page](https://github.com/emulsify-ds/emulsify_tools/releases).

### Publishing the release to Drupal.org

- Manually push the `1.1.x` branch and matching numeric tag to the Drupal.org Git remote.
- Go to the [Releases tab for Emulsify Tools](https://www.drupal.org/node/3094752/edit/releases) on Drupal.org. (You'll need to be a maintainer to access this page.)
- Click "Add new release"
- Select the tag for the latest release and click Next
- Copy the release notes from the GitHub releases page, and reformat them according to the wysiwyg options
- Select the appropriate release type(s) (Bug fixes/New features).
- Click Save
- Back on the Releases tab, select the new release as the "Supported" and "Recommended" release. Deselect any others.
- Save, and go to the projects main page to verify that the new release is displayed in the green box so that future builds will pull it by default.
