## INTRODUCTION

The Navigation Extra Tools module provides a "Tools" submenu on the new Navigation toolbar with options for clear cache, run cron, and run updates. This performs exactly the same functions as the "Admin Toolbar Extra Tools" submodule of Admin Toolbar..

Admin Toolbar Extra Tools was a useful submodule for providing extra useful functions for site administrators. This functionality is missing from the new Navigation module in Drupal core. This module is designed to provide the same functionality for the new Navigation.

Functions available:

- Clear cache (all caches, or individual caches)
- Run cron
- Run database updates

If the [Devel](https://www.drupal.org/project/devel) module is installed, a
Development menu will be added under Tools. This can be configured by the Devel
Toolbar Settings page.

## REQUIREMENTS

This module requires the core Navigation module to be enabled.

## INSTALLATION

Install as you would normally install a contributed Drupal module.

## CONFIGURATION

Currently no configuration in this module, but if Devel module enabled, will
show a development menu that can be configured in:

/admin/config/development/devel/toolbar

In Admin Toolbar Extra Tools, this list was hard coded. So the configurable list
initializes to the same items for consistency.

Note that the module adds new permissions, so users must be granted permission
to see parts of the Tools menu.

- Access navigation extra tools: cache flushing
- Access navigation extra tools: cron

## IMAGE CREDITS

The module uses the _wrench_ icon from [Phosphor Icons](https://phosphoricons.com/) in keeping with the design of the Navigation Toolbar.

Project logo designed by James Shields using [Inkscape](https://inkscape.org/), the open source drawing application, incorporating the Drupal logo and the wrench icon above.

## MAINTAINERS

Current maintainers for Drupal 10 and 11:

- [James Shields (lostcarpark)](https://www.drupal.org/u/lostcarpark)
