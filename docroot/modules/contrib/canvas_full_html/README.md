# Canvas Full HTML

Replaces Canvas's restricted text formats with the Full HTML text format in
Drupal Canvas (Experience Builder) WYSIWYG editors, enabling unrestricted
HTML editing capabilities. Can be easily toggled on/off via configuration.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/canvas_full_html).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/canvas_full_html).


## Table of contents

- Requirements
- Installation
- Configuration
- How it works
- CKEditor 5 compatibility
- Troubleshooting
- Maintainers


## Requirements

This module requires:

- Drupal 10.3 or 11
- PHP 8.1 or higher
- [Canvas](https://www.drupal.org/project/canvas) module
- CKEditor 5 (included in Drupal core)

The module creates its own dedicated `canvas_full_html` text format on install.
No manual text format setup is required.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

```bash
composer require drupal/canvas_full_html
drush en canvas_full_html
```


## Configuration

Configure the module at **Administration > Configuration > Content >
Canvas Full HTML** (`/admin/config/content/canvas-full-html`).

### Settings

- **Enable Full HTML format in Canvas**: Toggle to enable or disable the
  `canvas_full_html` text format replacement.
  - **Enabled** (default): Canvas WYSIWYG editors use the dedicated
    `canvas_full_html` text format with a Canvas-safe CKEditor 5 toolbar.
  - **Disabled**: Canvas uses its default restricted formats
    (`canvas_html_block`, `canvas_html_inline`).

The `canvas_full_html` text format toolbar can be customised at any time via
**Administration > Configuration > Content > Text formats**
(`/admin/config/content/formats/manage/canvas_full_html`). Both Drupal core
and contrib CKEditor 5 plugins (e.g. `ckeditor5_plugin_pack`) are supported.

When enabled, the module automatically:

1. Replaces Canvas's restricted text formats with `canvas_full_html` for all
   components that use `contentMediaType: text/html` props.
2. Pre-loads all enabled CKEditor 5 plugin libraries so contrib plugins are
   available before Canvas's React editor initialises.
3. Includes CSS and JavaScript fixes to ensure CKEditor 5 toolbar dropdowns
   display correctly within the Canvas interface.


## How it works

Canvas provides its own restricted text formats (`canvas_html_block` and
`canvas_html_inline`) for rich text editing within component props. These
formats limit the HTML tags and CKEditor features available to content editors.

### Text format replacement

This module uses `hook_canvas_storable_prop_shape_alter()` to intercept the
prop shape configuration and replace the Canvas text formats with
`canvas_full_html` before the component form is built. This replacement only
occurs when the module's setting is enabled.

### CKEditor toolbar fix

Canvas uses a React-based UI with Radix UI components that have
`overflow: hidden` on scroll containers. This can cause CKEditor 5 toolbar
dropdowns (like the "Show more items" button) to be clipped.

The module solves this by:

1. Using `hook_library_info_alter()` to attach CSS and JavaScript to the
   Canvas UI library (since Canvas bypasses normal page rendering).
2. JavaScript detects when a CKEditor toolbar dropdown is expanded
   (via `aria-expanded` attribute).
3. CSS applies `overflow: visible` to the Radix scroll containers only when
   a dropdown is open, preserving normal scroll behavior otherwise.


## CKEditor 5 compatibility

Both Drupal core and contrib CKEditor 5 plugins are supported. The module
pre-loads all libraries enabled on the `canvas_full_html` editor config before
Canvas's React editor initialises, so plugins from modules such as
`ckeditor5_plugin_pack` or `ui_icons_ckeditor5` work without any extra setup.

**Default toolbar items** (shipped with this module):

- Text style: bold, italic, underline, strikethrough, superscript, subscript,
  removeFormat
- Heading
- Link
- Lists: bulletedList, numberedList
- blockQuote, horizontalLine
- sourceEditing

You can add any additional toolbar items at
`/admin/config/content/formats/manage/canvas_full_html`.

Note that `editor.editor.canvas_full_html` is a dedicated format. Changes to
it only affect Canvas editors and do not impact the site's `full_html` or any
other text format used in regular Drupal content editing.


## Troubleshooting

### CKEditor toolbar dropdown is cut off

Clear all caches after enabling the module:

```bash
drush cr
```

The module includes CSS that fixes overflow issues that can cause CKEditor
toolbar dropdowns to be clipped by the Canvas UI scroll containers.

### Full HTML format not being used

1. Ensure the setting is enabled at `/admin/config/content/canvas-full-html`
2. Clear all caches: `drush cr`
3. Add a **new** component instance (existing instances may have cached
   settings)
4. Ensure the `canvas_full_html` text format exists at
   `/admin/config/content/formats/manage/canvas_full_html`

### Uninstalling the module

Uninstalling this module will delete the `canvas_full_html` text format
config entity. Any Canvas page content that was saved using this format will
lose its text format association. It is recommended to switch Canvas components
back to the default format before uninstalling.

### Switching back to Canvas default formats

1. Go to `/admin/config/content/canvas-full-html`
2. Uncheck "Enable Full HTML format in Canvas"
3. Save configuration (caches are automatically cleared)
4. Add new component instances to use Canvas default formats

### Module not affecting existing components

The prop shape alteration happens when components are loaded. Existing component
instances created with a different format setting will retain their original
text format. Create new component instances to use the current setting.


## Maintainers

- Zeeshan Khan - [zeeshan_khan](https://www.drupal.org/u/zeeshan_khan)

Supporting organization:

- [Specbee](https://www.drupal.org/specbee)
