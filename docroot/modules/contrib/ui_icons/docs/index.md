# Introduction

The UI Icons module leverages Drupal's [icon API](https://www.drupal.org/project/drupal/issues/3471494).

## Installation

Install as you would normally install a contributed Drupal module.  
See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules)
for further information.

!!! note "Drupal compatibility"

    The **1.0.x** branch is for **Drupal 10.4 to 11.0** and include a backport
    of Drupal core Icon API.  
    This branch will be **minimally** maintained.

    The **1.1.x** branch is for **Drupal 11.1+** and upgrade from previous
    version.  
    This branch include empty backport and Iconify sub modules to be removed.

## Usage

See the [Drupal Icon API documentation](https://www.drupal.org/docs/develop/drupal-apis/icon-api) to add your icon pack.  
Links to quick examples of icon packs are provided below.

### Starter for icon pack provider

Third party themes include definition for some icon pack:

- [UI Suite Bootstrap 5.x](https://www.drupal.org/project/ui_suite_bootstrap) include [Bootstrap icons](https://git.drupalcode.org/project/ui_suite_bootstrap/-/blob/5.1.x/ui_suite_bootstrap.icons.yml)
- [UI Suite DSFR](https://www.drupal.org/project/ui_suite_dsfr) include [DSFR icons](https://git.drupalcode.org/project/ui_suite_dsfr/-/blob/1.1.x/ui_suite_dsfr.icons.yml)
- [UI Suite USWDS](https://www.drupal.org/project/ui_suite_uswds) include [USWDS icons](https://git.drupalcode.org/project/ui_suite_uswds/-/blob/4.0.x/ui_suite_uswds.icons.yml)
- [UI Suite DaisyUI](https://www.drupal.org/project/ui_suite_daisyui) include [Hero icons](https://git.drupalcode.org/project/ui_suite_daisyui/-/blob/4.0.x/ui_suite_daisyui.icons.yml)

You can find examples in the external project [UI Icons Example](https://gitlab.com/ui-icons/ui-icons-example).

These examples serve as a starting point for adding icons to your Drupal installation.  
We strive to provide ready-to-use examples that can be easily adapted to your use case.

These examples include widely used third-party icon packs like:

- [Bootstrap Icons](https://icons.getbootstrap.com)
- [FontAwesome](https://fontawesome.com/icons)
- [Feather Icons](https://feathericons.com)
- [Heroicons](https://heroicons.com)
- [Material Symbols](https://fonts.google.com/icons)
- [Octicons](https://primer.style/foundations/icons)
- [Phosphor Icons](https://phosphoricons.com)
- [Remix icon](https://remixicon.com)
- [Delta icons](https://delta-icons.github.io)
- [Evil icons](https://evil-icons.io)
- [Maki icons](https://labs.mapbox.com/maki-icons)
- [Lucide icons](https://lucide.dev)

Icon pack builders:

- [IcoMoon](https://icomoon.io) - Import your icons or choose from existing sets

Specific Design System Icons:

- [USWDS](https://designsystem.digital.gov) - US federal government Design System
- [DSFR](https://www.systeme-de-design.gouv.fr) - French government Design System

We even make the [Drupal core Icon](https://gitlab.com/ui-icons/ui-icons-example/-/tree/main/ui_icons_drupal) available!

You can contribute a pull request (PR) for any third-party icon pack to the project!

## Drupal Implementations

Different submodules provide implementations for Fields, Field Links, Menus,
CKEditor and Media.

### Field UI

Enable the `UI Icons Fields` module to add a new field of type **Icon** with
specific options and a formatter.

For integration with fields of type **Link**, be sure to select the `Link Icon`
widget and formatter under **Manage form display** and **Manage display**.

### Menu

Enable the `UI Icons for Menu` module to be able to add an Icon to a menu item.
Note
After enabling the module, edit a menu item to access the Icon selection.

Compatibility with Drupal core Navigation module require your icon pack to
include a `class` as settings in `*.icons.yml`, ie:

```yaml
    my_icon_pack:
    settings:
        class:
        title: "Class"
        type: "string"
```

And this class must be in the icon template.

There is no implementation from the Node edit form yet.

### CKEditor5

Enable the `UI Icons CKEditor 5` module, then navigate to:

- Administration >> Configuration >> Content authoring

Configure your text format to add the `Icon` button and enable the `Embed icon`
filter.

!!! warning "Third party incompatibility"

    `UI Icons CKEditor 5` is not compatible with [`ckeditor5_icons` module](https://www.drupal.org/project/ckeditor5_icons)

### Media

Enable the submodule `UI Icons media` to allow usage with Media.

A media type Icon can be created with source **Icon**.

### Icon selector

The default Icon selector is based on an autocomplete Drupal field.

The submodule `UI Icons Picker` provides a more advanced selector of type
`icon_picker`.

### Icon Library

Enable the submodule `UI Icons library` to browse your icons:

- Administration >> Appearance >> UI libraries >> Icons packs

## Modules Implementations

Different submodules provide implementations for third party modules.

### Link Attributes

For support with the [Link Attributes widget](https://www.drupal.org/project/link_attributes),
enable the `UI Icons Link Attributes` module.

### Linkit

For support with [Linkit](https://www.drupal.org/project/linkit), enable the
`UI Icons Linkit` and `UI Icons Linkit Attributes` modules

### UI Patterns

Enable the submodule `UI Icons for UI Patterns` to allow usage with
[UI Patterns 1 or 2](https://www.drupal.org/project/ui_patterns).

!!! warning "Data from field"

    UI Patterns field source is considered experimental.  
    Using Data from an other field for an icon property is subject to change and
    should be used for tests only.

!!! danger "UI Patterns 1.x"

    UI Patterns 1 support is limited and will be removed when UI Patterns 2
    stable is released.

## Maintainers

Current maintainers:

- Jean Valverde - [mogtofu33](https://www.drupal.org/u/mogtofu33)
- Pierre Dureau - [pdureau](https://www.drupal.org/user/1903334)
- Florent Torregrosa - [Grimreaper](https://www.drupal.org/user/2388214)

Supporting organizations:

- [Beyris](https://www.drupal.org/beyris) - We are leading impactful open-source
projects and we are providing coding, training, audit and consulting.
