Google Translator
=================

INTRODUCTION
------------

This module brings the power of Google Translate's Website Translator into
Drupal, providing an instant translated version of your site's text. It also
provides an administration interface for setting allowed languages,
widget display modes, and disclaimer for the user to accept before accessing
the widget.

@see https://translate.google.com/manager/website/?hl=en

* For a full description of the module, visit the project page:
  https://www.drupal.org/project/google_translator

* To submit bug reports and feature suggestions, or to track changes:
  https://www.drupal.org/project/issues/1473726


REQUIREMENTS
------------

Since this provides a block, you must enable Block module.

INSTALLATION
------------

* Via Composer, see: [Installing modules' Composer
  dependencies](https://www.drupal.org/docs/8/extending-drupal-8/installing-modules-composer-dependencies)

* Manually, see: [Installing Drupal 8
  Modules](https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules)

* Enable Google Translator in your site's modules admin.

CONFIGURATION & USAGE
---------------------

* To configure, go to: /admin/config/regional/google-translator

* To use as a block, add the Google Translator language selector to any region.


STYLING
-------

To unset the default styles provided by drupal.dialog so you can style the modal
youself, add this libraries override to your theme's `theme.info.yml` file.

```
libraries-override:
  core/drupal.dialog:
    css:
      theme:
        assets/vendor/jquery.ui/themes/base/theme.css: false
```
