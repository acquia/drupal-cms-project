# Theme Plugin Manager

The Theme plugin manager discovers YAML-based plugins that define alternative
styling for a modeler canvas. Every modeler ships a default look and feel that
is used regardless of which Model Owner it renders. A theme lets a site use a
different look and feel for a specific owner/modeler combination without the
modeler having to know about it.

Any module can provide themes: the Model Owner, the Modeler, or a third module
that only wants to contribute styling.

## Plugin manager details

| Property | Value |
|----------|-------|
| **Service ID** | `plugin.manager.modeler_api.theme` |
| **Class** | `Drupal\modeler_api\Plugin\ThemePluginManager` |
| **Discovery** | YAML file (`MODULE.modeler_api.themes.yml`) |
| **Value object** | `Drupal\modeler_api\Theme` |
| **Alter hook** | `hook_modeler_api_theme_info_alter()` |
| **Cache tag** | `modeler_api_theme_plugins` |

## YAML file structure

Create a file named `my_module.modeler_api.themes.yml` in your module's root
directory:

```yaml
my_dark_theme:
  label: 'Dark'
  description: 'A dark canvas with light strokes.'
  libraries:
    - my_module/dark_theme
  owners:
    - eca
  modelers:
    - workflow_modeler

my_print_theme:
  label: 'Print friendly'
  libraries:
    - my_module/print_theme
```

### Top-level keys

Each top-level key in the YAML file defines a theme. Multiple themes can be
defined in a single file.

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `label` | `string` | Yes | Human-readable name shown in the settings form (translatable) |
| `description` | `string` | No | Human-readable description (translatable) |
| `libraries` | `string[]` | Yes | Drupal asset library names, e.g. `my_module/dark_theme` |
| `owners` | `string[]` | No | Model Owner plugin IDs this theme is limited to. Omit for all owners |
| `modelers` | `string[]` | No | Modeler plugin IDs this theme is limited to. Omit for all modelers |
| `weight` | `int` | No | Order among themes competing for [automatic selection](#automatic-selection). Lower first, defaults to `0` |

A definition without a `label` or without at least one entry in `libraries` is
skipped, because such a theme could neither be selected nor have any effect.

The IDs `auto` and `default` are reserved: they are the two non-theme values of
the Theme setting, so a theme using one of them could never be told apart from
the option it shadows. Such a definition is skipped during discovery, just like
one without a label.

A single string is accepted as a shorthand wherever a list is expected, so
`libraries: my_module/dark_theme` is equivalent to the list form.

### Restricting a theme

The `owners` and `modelers` keys are independent filters and an empty or
missing list means "all". This gives four combinations:

| `owners` | `modelers` | Theme is offered for |
|----------|------------|----------------------|
| omitted | omitted | Every owner/modeler combination |
| `[eca]` | omitted | Every modeler, but only for the `eca` owner |
| omitted | `[bpmn_io]` | Every owner, but only in the `bpmn_io` modeler |
| `[eca]` | `[bpmn_io]` | Only `eca` in `bpmn_io` |

These keys do double duty: they also decide whether a theme can be selected
[automatically](#automatic-selection), and how specific it is when several
themes compete. The first row -- neither key set -- is the one case that is
never selected automatically.

## Selecting a theme

A site builder selects the theme per owner/modeler combination on the Modeler
API settings form at
**Administration > Configuration > Workflow > Modeler API**
(`/admin/config/workflow/modeler_api`).

The **Theme** select for each combination is built from
`ThemePluginManager::getThemesFor()`, so it only lists themes that actually
apply to that combination. Two options come before them and are always
available:

| Value | Constant | Meaning |
|-------|----------|---------|
| `auto` | `Form\Settings::THEME_OPTION_AUTO` | Let the Modeler API pick an applicable theme, and apply nothing if there is none |
| `default` | `Form\Settings::THEME_OPTION_DEFAULT` | Keep the modeler's own look and feel untouched |

Anything else is a theme ID, which pins that one theme explicitly.

The selection is stored in `modeler_api.settings` under
`owner_modeler.OWNER_ID.MODELER_ID.theme`. When the key is absent -- the module
ships no default configuration, so that is the state of a fresh site -- it is
read as `auto`.

If a stored theme is no longer discoverable -- its module was uninstalled, or
its `owners`/`modelers` restrictions changed -- the select falls back to
**Default** instead of failing validation. It deliberately does not fall back
to **Automatic**: a theme that went away should stop applying, not be replaced
by a different one.

### Automatic selection {: #automatic-selection }

`auto` is resolved by `ThemePluginManager::resolveTheme()` every time a model
is rendered. Nothing is written to configuration, which is what makes a theme
take effect the moment its module is installed and stop the moment it is
uninstalled. There is no value to drift, to be reverted by a configuration
import, or to go stale.

A theme has to state that it was built for the combination before it can be
picked:

1. **Explicit attribution is required.** Only a theme declaring a non-empty
   `owners` list or a non-empty `modelers` list is a candidate. A theme that
   declares neither applies everywhere by design, and choosing it
   automatically would let any module change the styling of every canvas on
   the site merely by being installed. Such a theme stays available as an
   explicit choice in the settings form -- it is simply never picked on its
   own, no matter how low its `weight` is.
2. **It has to apply.** The `owners`/`modelers` restrictions are evaluated with
   the same `Theme::appliesTo()` the settings form uses.

Among the candidates, the most specific one wins:

1. A theme naming **both** an owner and a modeler beats a theme naming only one
   of the two.
2. Within the same specificity, the lowest `weight` wins. It defaults to `0`
   and follows the usual Drupal convention, so a negative weight moves a theme
   to the front.
3. At equal weight, the alphabetically first theme ID wins.

Discovery order is deliberately not part of this. It follows module weight and
would silently change when an unrelated module is installed, which is no basis
for a documented contract -- hence the theme ID as the final tie-break.

If nothing qualifies, `resolveTheme()` returns `NULL` and the combination
behaves exactly like **Default**: the modeler keeps its own look and feel.

```php
/** @var \Drupal\modeler_api\Plugin\ThemePluginManager $themeManager */
$themeManager = \Drupal::service('plugin.manager.modeler_api.theme');

// The theme that 'auto' would apply, or NULL for the modeler's own styling.
$theme = $themeManager->resolveTheme('eca', 'workflow_modeler');
```

## How the libraries get attached

`Api::edit()` is the single place that builds the render array for both editing
and viewing a model (`Api::view()` delegates to it). After the modeler has
produced its render array, the API resolves the configured theme and appends
its libraries to `#attached['library']`:

```php
$build['#attached']['library'][] = 'my_module/dark_theme';
```

Because this happens centrally, every modeler benefits without any change on
its side. An unknown or no longer applicable theme ID is ignored silently, so a
stale setting never breaks the modeler.

The resolved theme ID is also exposed to the client in
`drupalSettings.modeler_api.theme`, so a JavaScript-based modeler UI can react
to it -- for example to pick a matching set of node colors that cannot be
expressed in CSS alone. The value is `default` when no theme is applied. It is
always the theme that is really applied, so `auto` never reaches the client:
the setting is resolved to a concrete theme ID first.

## Writing the CSS for a theme

A theme is nothing more than a regular Drupal asset library, declared in your
module's `*.libraries.yml`. There is no special file format and no build step.

```yaml
# my_module.libraries.yml
dark_theme:
  version: VERSION
  css:
    theme:
      css/dark-theme.css: {}
```

Guidelines for the CSS itself:

- **Declare the CSS in the `theme` category.** Drupal orders the CSS of a page
  in two stages: first by category (`base`, `layout`, `component`, `state`,
  `theme`), then, within a single category, by the order in which the libraries
  were attached. The `theme` category is ordered last, which makes it the safest
  place for a theme: its CSS is never loaded before the styling it is meant to
  override. A modeler may well declare its own canvas CSS in the `theme`
  category too -- the Workflow Modeler does -- in which case both land in the
  same category and the second stage decides. That still resolves in favor of
  the theme, because `Api::attachTheme()` appends the theme's libraries after
  the libraries the modeler attached itself. Either way the theme is loaded
  last, so an equally precise rule from the theme wins.
- **Override, do not replace.** The modeler library is still attached, so write
  rules that override selected properties (colors, fonts, borders, shadows)
  rather than restating the full layout. A theme that only sets colors keeps
  working when the modeler changes its layout.
- **Scope the rules to the modeler's wrapper element.** Each modeler renders
  its canvas inside a container with a stable ID or class, for example
  `#workflow-modeler-wrapper` for the Workflow Modeler. Prefixing every rule
  with that selector keeps the theme from leaking into the rest of the admin
  page.
- **Keep the selectors no more precise than the override needs.** Matching the
  selector of the rule you override is enough once the ordering is in place.
  Reaching for `!important` makes the theme impossible to override in turn.
- **Prefer CSS custom properties when the modeler exposes them.** If a modeler
  defines its colors as custom properties on its wrapper element, a theme only
  needs to redefine those properties, which is far more robust than overriding
  individual rules.

A theme may declare more than one library, which is useful to share a common
base of rules between several themes:

```yaml
my_module_dark:
  label: 'Dark'
  libraries:
    - my_module/theme_base
    - my_module/theme_dark
```

### A modeler exposing its own default styling as a theme

A modeler is free to ship its own default look and feel as a theme as well.
That makes the default explicit and selectable, and it gives other modules a
library to depend on when they build a variation of it:

```yaml
# modeler.modeler_api.themes.yml
workflow_modeler_default:
  label: 'Workflow Modeler default'
  description: 'The styling the Workflow Modeler ships with.'
  libraries:
    - modeler/react-ui-theme-default
  modelers:
    - workflow_modeler
```

Note that this theme is additive like every other one -- selecting it attaches
its library on top of the modeler's own. It is therefore only equivalent to the
**Default** option if the library holds exactly the styling the modeler already
loads.

Because it names a modeler, such a theme is also a candidate for
[automatic selection](#automatic-selection), as one of the less specific kind:
a theme naming both an owner and a modeler outranks it, and one naming only an
owner competes with it on weight and theme ID. Give it a high `weight` to keep
it behind the themes that are meant to win.

## Theme value object

The `Drupal\modeler_api\Theme` class is a readonly value object:

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Theme ID |
| `getLabel()` | `string` | Human-readable label |
| `getDescription()` | `string` | Human-readable description, or an empty string |
| `getProvider()` | `string` | Module that defined this theme |
| `getLibraries()` | `string[]` | Asset library names |
| `getOwners()` | `string[]` | Model Owner plugin IDs, empty means all |
| `getModelers()` | `string[]` | Modeler plugin IDs, empty means all |
| `getWeight()` | `int` | Order among automatic selection candidates, `0` by default |
| `appliesTo($ownerId, $modelerId)` | `bool` | Whether the theme can be used for a combination |

## ThemePluginManager API

| Method | Return | Description |
|--------|--------|-------------|
| `getAllThemes($reload)` | `Theme[]` | All discovered themes |
| `getTheme($id)` | `?Theme` | A single theme by ID |
| `getThemesFor($ownerId, $modelerId)` | `Theme[]` | All themes applying to a combination |
| `resolveTheme($ownerId, $modelerId)` | `?Theme` | The theme `auto` picks for a combination, or NULL |

```php
/** @var \Drupal\modeler_api\Plugin\ThemePluginManager $themeManager */
$themeManager = \Drupal::service('plugin.manager.modeler_api.theme');

// Everything that could be offered for ECA in the Workflow Modeler.
$available = $themeManager->getThemesFor('eca', 'workflow_modeler');

// A single theme, or NULL when no module provides it.
$theme = $themeManager->getTheme('my_dark_theme');

// What the automatic option resolves to for that combination.
$automatic = $themeManager->resolveTheme('eca', 'workflow_modeler');
```

## Alter hook

Other modules can change theme definitions at discovery time:

```php
/**
 * Implements hook_modeler_api_theme_info_alter().
 */
function my_module_modeler_api_theme_info_alter(array &$definitions): void {
  // Make a theme that was built for one owner available everywhere.
  if (isset($definitions['my_dark_theme'])) {
    unset($definitions['my_dark_theme']['owners']);
  }
  // Add another library to an existing theme.
  if (isset($definitions['my_print_theme'])) {
    $definitions['my_print_theme']['libraries'][] = 'my_module/print_extras';
  }
}
```
