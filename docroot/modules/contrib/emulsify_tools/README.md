> [!WARNING]  
> Work in progress.
> 
# Emulsify Tools module

This module provides two Twig extensions used in the [Emulsify Design System](https://github.com/emulsify-ds/) as well as a theme generation Drush command.

## Usage

---

### Drush

`drush emulsify_tools:bake [theme_name]`

`drush emulsify [theme_name]`

### BEM Twig Extension

Twig function that inserts static classes into Pattern Lab and adds them to the Attributes object in Drupal

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

Twig function that merges with template level attributes in Drupal and prevents them from trickling down into includes.

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

### Committing Changes

To facilitate automatic semantic release versioning, we utilize the [Conventional Changelog](https://github.com/conventional-changelog/conventional-changelog) standard through Commitizen. Follow these steps when commiting your work to ensure a better tomorrow.

1. Stage your changes, ensuring they encompass exactly what you wish to change, no more.
2. Run `yarn commit` and follow the prompts to craft the perfect commit message.
3. _Rejoice!_ For now your commit message will be used to create the changelog for the next version that includes that commit.

## Release

---

There's a two-step process to publish a new release to [the project page](https://www.drupal.org/project/emulsify_tools) on Drupal.org.

1. Cut a release on GitHub
2. Select the generated tag for the release on Drupal.org, and set it as the "recommended" release.

### Creating a release on GitHub

- Once one or more PRs are merged into the development branch, [create a "Release" PR](https://github.com/emulsify-ds/emulsify_tools/compare/main...feature-branch) to merge the latest from that branch into `main`.
- As soon as that PR is merged, a [GitHub action](https://github.com/emulsify-ds/emulsify_tools/actions) will kick off to cut a release based on the commit messages in that release.
  - _Note: This workflow will also push the new tag to drupal.org so that you can select it in the next section._
- When that is finished, you should see the new release listed on the [Releases page](https://github.com/emulsify-ds/emulsify_tools/releases) for the repository.

### Publishing the release to Drupal.org

- Go to the [Releases tab for the Emulsify Twig project](https://www.drupal.org/node/3094752/edit/releases) on drupal.org. (You'll need to be a maintainer to access this page.)
- Click "Add new release"
- Select the tag for the latest release and click Next
- Copy the release notes from the GitHub releases page, and reformat them according to the wysiwyg options
- Select the appropriate release type(s) (Bug fixes/New features).
- Click Save
- Back on the Releases tab, select the new release as the "Supported" and "Recommended" release. Deselect any others.
- Save, and go to the projects main page to verify that the new release is displayed in the green box so that future builds will pull it by default.
