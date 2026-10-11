# Custom Field

Dynamic custom field types with extensive widget and formatter plugin support. 
A highly performant & scalable alternative to paragraphs and entity reference by
storing data in a single table.

## Features

- Multiple-value fields without entity references
- Inline field widgets using a customizable css-flexbox-based layout system
- Multiple field formatters: CustomField (custom theme hook), Inline, HTML List,
  Table, Custom Template (similar to views' field rewrite functionality)
- Clone field settings from **ANY** entity type
- Add/Remove columns to fields with existing data.
  [See documentation](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/custom-field/addremove-columns-to-custom-fields-with-existing-data)
- Performance & scalability - Eliminates unnecessary field table bloat and 
  configuration files
- Reduce overhead - May replace the need for additional contrib modules

## Integrations

- [Feeds](https://www.drupal.org/project/feeds)
- [Linkit](https://www.drupal.org/project/linkit)
- [GraphQL Compose](https://www.drupal.org/project/graphql_compose)
- [Search API](https://www.drupal.org/project/search_api)

## Migrate to Custom fields

You can use the [Field Updater Service](https://www.drupal.org/project/field_updater_service)
module to map 1 or more fields in a configuration entity and use the provided 
service in an update hook from your custom module.

## Included sub-modules

- custom_field_graphql - [GraphQL compose](https://www.drupal.org/project/graphql_compose)
  integration.
- custom_field_linkit - [Linkit](https://www.drupal.org/project/linkit)
  integration.
- custom_field_media - Provides a *Media Library* widget.
- custom_field_search_api - Enhances [Search API](https://www.drupal.org/project/search_api)
  integration.
- custom_field_viewfield - Provides the ability to reference and display views.
- custom_field_jsonapi - Provides normalizers for jsonapi integration.

## Field types, widgets & formatters

- [Overview of included field types, widgets & formatters](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/custom-field/field-types-widgets-formatters)
- [Extending Custom Field widget plugins](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/custom-field/extending-custom-field-widget-plugins)
- [Extending Custom Field formatter plugins](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/custom-field/extending-custom-field-formatter-plugins)

## Why this module?

In some cases, Drupal's field api for single value fields is overkill for
storing simple field data that would be better to consolidate in a single table.
One *Custom Field* can contain many columns in a single table which can lead to
a substantial boost in performance by eliminating unnecessary joins and allowing
for simpler configuration management.

## Requirements

This module requires no modules outside of Drupal core.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Maintainers

- Andy Marquis - [apmsooner](https://www.drupal.org/u/apmsooner)
