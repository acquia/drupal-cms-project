# Drupal CMS Installer
An install profile that implements the installation experience for Drupal CMS. In addition to the nuts-and-bolts installation logic, it also contains a theme which is tightly coupled to the profile itself.

⚠️ **This package is an internal part of Drupal CMS.** It has no API or backwards compatibility promise, and anything in it may be changed, or removed outright, at _any_ time without notice. If you use it outside of Drupal CMS, you do so at your own risk! ⚠️ If you want to customize this profile, your best option is to fork it under a new name.

This profile allows you to choose a site template to apply when setting up Drupal CMS, apply it, and then uninstall itself. It's meant to be used once, then removed. Once you have installed Drupal CMS, you can remove this profile with Composer:

```shell
composer remove drupal/drupal_cms_installer
```

## Bugs and Feedback
If you encounter any bugs or have feedback about the installer, please file an issue in the [Drupal CMS issue queue](https://www.drupal.org/project/issues/drupal_cms).
