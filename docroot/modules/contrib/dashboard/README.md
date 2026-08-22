# Dashboard

Dashboard module aims to provide users with a centralized interface to access
key information and essential tools after logging into the system. The main
objective is to enhance the user experience by offering a quick and personalized
overview of relevant aspects of the website or application.

A Dashboard is a custom Drupal entity that typically includes widgets or blocks
that display relevant data such as traffic statistics, content summaries,
important notifications, and more. The customization capability allows users to
adjust the layout and content of the dashboard according to their preferences,
ensuring that the most relevant information is easily accessible.

Users may wear different hats, so it is possible to define multiple dashboards
and manage access to them through permissions for an improved experience.

For the description of the module visit the
[Dashboard module page](https://www.drupal.org/project/dashboard).

To submit bug reports and feature suggestions or to track changes, visit the
[issues queue](https://www.drupal.org/project/issues/dashboard).


## Requirements

This module requires no modules outside of Drupal core.
It requires Layout Builder.


## Installation

*If you intend to contribute to Dashboard, skip this step and use the
"Contributing" instructions instead*

Install with composer: `composer require drupal/dashboard` then enable the
module.


## Contributing

- Follow the
  [Git instructions](https://www.drupal.org/project/dashboard/git-instructions)
  to clone the Dashboard module to your site.


## Configuration

To create a new Dashboard:
- Navigate to Administration > Structure > Dashboards
- Click "Add dashboard"
- Provide a label and (optional) description
- Click "Save"
- Click "Edit layout" for the new Dashboard
- Configure the new Dashboard using
  [Layout Builder](https://www.drupal.org/docs/8/core/modules/layout-builder)

Currently, this module does NOT provide any Dashboards out of the box. However,
it provides a few additional features that can be placed in a Dashboard:

- A "Dashboard Text" block with a text field for custom text.
- A "Site status" block to show any errors or warnings as the Status Report page does.
- Views with block displays that might be useful: e.g. "My recent content", "My own drafts", among others.


## Maintainers

- Christian López Espínola 'penyaskito' https://www.drupal.org/u/penyaskito
- Matthew Tift 'mtift' https://www.drupal.org/u/mtift
- Pablo López 'plopesc' https://www.drupal.org/u/plopesc
- Cristina Chumillas 'ckrina' https://www.drupal.org/u/ckrina
- Adam Globus-Hoenich 'phenaproxima' https://www.drupal.org/u/phenaproxima
