# Tagify Link

Tagify Link is a submodule of Tagify. It provides a "Tagify link" field
widget that renders the URI of a Link field as a single Tagify tag instead of
a plain text input. As the user types, the widget fetches matching
suggestions and shows them in an autocomplete dropdown, while still storing a
plain URI value in the field.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/tagify).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/search/tagify).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires the following modules:

- [Tagify](https://www.drupal.org/project/tagify)
- Link (Drupal core)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

The Tagify.js library is provided by the Tagify module. See the Tagify module's
README for the available options to serve the library from a CDN or locally.


## Configuration

Set a Link field Widget to use "Tagify link", under the desired content type
form display settings. For example for Article, this is under
*/admin/structure/types/manage/article/form-display*.

Under the widget settings, you can set Autocomplete matching to be
"Starts with" or "Contains", and the maximum number of autocomplete
suggestions to show in the dropdown.


## Maintainers

- Pedro Cambra - [pcambra](https://www.drupal.org/u/pcambra)
