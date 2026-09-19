# Drupal CMS Helper
Provides functionality for Drupal CMS that is not yet in Drupal core or dependencies. This has no dependencies apart from core.

## Installation
This module is automatically included with Drupal CMS and should not be uninstalled.

If you're not using Drupal CMS, you can still use this module in your own project. Install it with Composer, like any other module.

## Developer Tools
This module includes functionality aimed squarely at recipe developers:
* A `site:export` command for Drush which exports the current site's configuration and content as a recipe. (This can also be done programmatically with the `\Drupal\drupal_cms_helper\SiteExporter` service).
* A `--generic` option for Drush's `config:export` command, which removes the `_core` and `uuid` keys from exported configuration because they should never be included in recipe-provided configuration.
* A `setDefaultImage` config action for image fields, so that they can choose a default image that doesn't exist yet (i.e., because it is included in a recipe's default content).

## Bugs and Feedback
If you encounter any bugs or have feedback about this module, please file an issue in the [Drupal CMS issue queue](https://www.drupal.org/project/issues/drupal_cms).
