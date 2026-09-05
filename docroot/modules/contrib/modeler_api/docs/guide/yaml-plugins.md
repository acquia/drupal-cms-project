# YAML Plugin Definitions

The Modeler API provides four YAML-based plugin types that allow any module to
contribute metadata without writing PHP code. These are ideal for curating
component palettes, defining ordering constraints, providing template tokens,
and styling the modeler canvas.

## Contexts {: #contexts }

Contexts define which components are available in a particular use case. They
allow modeler UIs to show a focused subset of plugins rather than the full
list.

### File naming

Place a YAML file named `my_module.modeler_api.contexts.yml` in your module's
root directory.

### Structure

```yaml
my_context:
  topic: 'Description of this context'
  model_owner: target_owner_id
  includes:
    - base_context_id
  components:
    start:
      plugins:
        - event_plugin_1
        - event_plugin_2
    element:
      plugins:
        - action_plugin_1
        - action_plugin_2
    link:
      plugins:
        - condition_plugin_1
```

### Key reference

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `topic` | `string` | Yes | Human-readable name (shown in UI) |
| `model_owner` | `string` | Yes | Target Model Owner plugin ID |
| `includes` | `string[]` | No | Other context IDs to inherit from |
| `components` | `object` | No | Plugin lists per component type |
| `components.{type}.plugins` | `string[]` | No | Plugin IDs available in this context |

### Valid component types

`start`, `subprocess`, `swimlane`, `element`, `link`, `gateway`, `annotation`

### Include resolution

Includes are resolved transitively. Given:

```yaml
base:
  topic: 'Base'
  model_owner: my_owner
  components:
    element:
      plugins: [a, b]

extended:
  topic: 'Extended'
  model_owner: my_owner
  includes: [base]
  components:
    element:
      plugins: [c]
```

The resolved `extended` context contains element plugins `[a, b, c]`.

### Multiple contexts per file

You can define multiple contexts in a single file. Each top-level key is a
separate context:

```yaml
context_a:
  topic: 'Context A'
  model_owner: my_owner
  components:
    start:
      plugins: [event_1]

context_b:
  topic: 'Context B'
  model_owner: my_owner
  includes: [context_a]
  components:
    start:
      plugins: [event_2]
```

### Real-world example

From `eca_ng.modeler_api.contexts.yml`:

```yaml
eca_base:
  topic: 'Commonly used ECA components'
  model_owner: eca
  components:
    start:
      plugins:
        - kernel:controller
    link:
      plugins:
        - eca_scalar
        - eca_count
        - eca_route_match
    element:
      plugins:
        - action_message_action
        - eca_token_set_value
        - eca_switch_account
        - eca_token_load_entity

eca_form:
  topic: 'Altering Drupal forms'
  model_owner: eca
  includes:
    - eca_base
  components:
    start:
      plugins:
        - form:form_build
        - form:form_submit
        - form:form_validate
    link:
      plugins:
        - eca_form_field_value
        - eca_form_field_exists
    element:
      plugins:
        - eca_form_add_textfield
        - eca_form_field_set_value
```

---

## Dependencies {: #dependencies }

Dependencies define predecessor constraints: which components can only be used
as successors of specific other components.

### File naming

Place a YAML file named `my_module.modeler_api.dependencies.yml` in your
module's root directory.

### Structure

```yaml
my_dependencies:
  model_owner: target_owner_id
  components:
    link:
      condition_plugin_id:
        - type: start
          id: required_event_id
    element:
      action_plugin_pattern:
        - type: start
          id: required_event_pattern
        - type: element
          id: required_action_pattern
```

### Key reference

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `model_owner` | `string` | Yes | Target Model Owner plugin ID |
| `components` | `object` | Yes | Rules per component type |
| `components.{type}.{pluginPattern}` | `array` | Yes | Plugin pattern (supports `*` wildcards) |
| `components.{type}.{pluginPattern}[].type` | `string` | Yes | Predecessor's component type name |
| `components.{type}.{pluginPattern}[].id` | `string` | Yes | Predecessor's plugin ID (supports `*` wildcards) |

### Wildcard support

Both the plugin ID key and the predecessor `id` value support glob-style
wildcards:

```yaml
my_dependencies:
  model_owner: my_owner
  components:
    element:
      my_form_*:          # Matches my_form_add, my_form_edit, etc.
        - type: start
          id: form:*      # Matches form:build, form:submit, etc.
```

### Semantic meaning

A dependency rule means: the plugin matching the key can **only** be used in a
model that has one of the listed predecessors as an ancestor (directly or
transitively).

For example:

```yaml
components:
  element:
    eca_form_add_textfield:
      - type: start
        id: form:form_build
```

This means: `eca_form_add_textfield` can only be used in a model where
`form:form_build` is the starting event. If a user creates a model starting
with `kernel:controller`, this action will be filtered out.

### Real-world example

From `eca_ng.modeler_api.dependencies.yml`:

```yaml
eca:
  model_owner: eca
  components:
    link:
      eca_route_match:
        - type: start
          id: kernel:controller
      eca_form_field_value:
        - type: start
          id: form:form_build
        - type: start
          id: form:form_submit
        - type: start
          id: form:form_validate
    element:
      eca_form_build_entity:
        - type: start
          id: form:form_submit
        - type: start
          id: form:form_validate
```

---

## Template tokens {: #template-tokens }

Template tokens define hierarchical token trees used in model templates. When
a model is marked as a template, these tokens can be used as placeholders
that are replaced when the template is instantiated.

### File naming

Place a YAML file named `my_module.modeler_api.template_tokens.yml` in your
module's root directory.

### Structure

```yaml
my_tokens:
  model_owner: target_owner_id
  tokens:
    root-key:
      name: 'Root Token Group'
      token: root-key
      children:
        child-key:
          name: 'Child Token'
          token: 'root-key:child-key'
          value: 'Default value'
          children:
            leaf:
              name: 'Leaf Token'
              token: 'root-key:child-key:leaf'
              value: 'Leaf value'
```

### Key reference

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `model_owner` | `string` | Yes | Target Model Owner plugin ID |
| `tokens` | `object` | Yes | Token tree (recursive) |

Each token entry:

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `name` | `string` | Yes | Human-readable name (translatable) |
| `token` | `string` | Yes | Token identifier (colon-separated path) |
| `value` | `string` | No | Sample or default value |
| `children` | `object` | No | Nested child tokens (same structure) |

### Token path convention

Tokens use colon-separated paths that mirror the tree structure:

```
root-key
  └── child-key         → token: "root-key:child-key"
       └── leaf         → token: "root-key:child-key:leaf"
```

The list builder wraps these in a `raw token` format like
`[template:root-key:child-key:leaf]` for use in template expressions.

### Merging across modules

When multiple modules define tokens for the same Model Owner, trees are deep
merged. For example, if module A defines:

```yaml
my_tokens:
  model_owner: my_owner
  tokens:
    config:
      name: Config
      token: config
      children:
        global:
          name: Global
          token: config:global
```

And module B defines:

```yaml
more_tokens:
  model_owner: my_owner
  tokens:
    config:
      name: Config
      token: config
      children:
        local:
          name: Local
          token: config:local
```

The merged result will have `config` with both `global` and `local` children.

### Real-world example

From `eca_ng.modeler_api.template_tokens.yml`:

```yaml
eca:
  model_owner: eca
  tokens:
    eca-template:
      name: 'ECA Templates'
      token: eca-template
      children:
        config:
          name: 'Configuration'
          token: 'eca-template:config'
          children:
            global:
              name: 'Global'
              token: 'eca-template:config:global'
              children:
                value:
                  name: 'Value'
                  token: 'eca-template:config:global:value'
                  children:
                    VALUE:
                      name: 'Value'
                      token: 'eca-template:config:global:value:VALUE'
                      value: 'Configurable value'
```

---

## Themes {: #themes }

Themes define alternative styling for the modeler canvas. Each theme points at
one or more Drupal asset libraries whose CSS overrides the look and feel the
modeler ships by default.

Unlike the three plugin types above, a theme does not belong to a single Model
Owner. It is selected per owner/modeler combination in the Modeler API settings
form, and it may restrict itself to a list of owners, a list of modelers, or
both. A theme that restricts itself to at least one of the two can also be
applied automatically, without anybody selecting it.

### File naming

Place a YAML file named `my_module.modeler_api.themes.yml` in your module's
root directory.

### Structure

```yaml
my_theme:
  label: 'Human-readable name'
  description: 'What this theme looks like.'
  libraries:
    - my_module/my_theme
  owners:
    - target_owner_id
  modelers:
    - target_modeler_id
  weight: 0
```

### Key reference

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `label` | `string` | Yes | Human-readable name shown in the settings form |
| `description` | `string` | No | Human-readable description |
| `libraries` | `string[]` | Yes | Drupal asset library names to attach |
| `owners` | `string[]` | No | Model Owner plugin IDs. Omit for all owners |
| `modelers` | `string[]` | No | Modeler plugin IDs. Omit for all modelers |
| `weight` | `int` | No | Order among automatic selection candidates, lower first. Defaults to `0` |

A definition without a `label` or without at least one library is skipped, and
so is one whose ID is `auto` or `default` -- those two are reserved for the
settings values described below.

### Selecting a theme

The **Theme** setting per owner/modeler combination takes three kinds of value:

| Value | Meaning |
|-------|---------|
| `auto` | Let the Modeler API pick an applicable theme. Also the behavior when nothing is stored |
| `default` | Keep the modeler's own look and feel |
| a theme ID | Pin that one theme explicitly |

Under `auto` the theme is resolved on every request, so no configuration is
written and a theme starts applying the moment its module is installed. Only a
theme that declares a non-empty `owners` or `modelers` list takes part: one
that declares neither applies everywhere by design, and picking it
automatically would let any module change the styling of every canvas on the
site merely by being installed. It stays selectable by hand.

Among the themes that qualify, a theme naming both an owner and a modeler beats
one naming only one of the two; then the lowest `weight` wins; then the
alphabetically first theme ID. Discovery order is deliberately not used,
because it follows module weight and would change when unrelated modules are
installed. If nothing qualifies, the modeler keeps its own look and feel.

### The asset library

The `libraries` entries are ordinary Drupal asset libraries from your module's
`*.libraries.yml`. Declare the CSS in the `theme` category, which is ordered
last, so that it is loaded after the CSS of the modeler:

```yaml
# my_module.libraries.yml
my_theme:
  version: VERSION
  css:
    theme:
      css/my-theme.css: {}
```

See the [Theme Plugin Manager](../plugin-managers/theme/index.md) page for the
full guidance on how to construct such CSS.

### Example

```yaml
dark:
  label: 'Dark'
  description: 'A dark canvas with light strokes.'
  libraries:
    - my_module/theme_base
    - my_module/theme_dark
  modelers:
    - workflow_modeler
  weight: -10

print_friendly:
  label: 'Print friendly'
  libraries:
    - my_module/theme_print
```

The `dark` theme is offered for every Model Owner, but only when the Workflow
Modeler is used. The `print_friendly` theme is offered everywhere.

Only `dark` is a candidate for automatic selection, because it names a modeler.
Its negative weight puts it ahead of other themes of the same specificity.
`print_friendly` restricts nothing, so it applies everywhere and has to be
selected by hand.

---

## Best practices

### Contexts

- Define a **base context** with commonly used plugins, then create specialized
  contexts that include the base.
- Keep contexts focused -- it's better to have many small contexts than one
  giant one.
- Use meaningful, descriptive `topic` values since they appear in the modeler
  UI.

### Dependencies

- Use wildcards sparingly -- overly broad patterns can be confusing.
- Only define dependencies when there is a genuine technical constraint (e.g.,
  a form action only works during form build events).
- Test dependency rules by switching contexts in the modeler UI.

### Template tokens

- Follow the colon-separated path convention consistently.
- Provide meaningful `value` defaults for leaf tokens to help users understand
  what the token represents.
- Group related tokens under common parent nodes for better organization.

### Themes

- Override selected properties -- colors, fonts, borders -- rather than
  restating the modeler's full layout, so the theme survives layout changes in
  the modeler.
- Scope every rule to the modeler's wrapper element so the theme does not leak
  into the rest of the admin page.
- Only set `owners` or `modelers` when the CSS genuinely depends on that owner
  or modeler. An unrestricted theme is reusable -- but it is also never applied
  automatically, so set them when the theme is meant to take effect on its own.
- Leave `weight` alone unless two themes of your own compete for the same
  combination. It only decides between automatic selection candidates.
- Split shared rules into their own library and list several libraries in one
  theme instead of duplicating CSS between variations.

## Alter hooks

All four YAML-based plugin types support alter hooks for programmatic
modifications:

```php
/**
 * Implements hook_modeler_api_context_info_alter().
 */
function my_module_modeler_api_context_info_alter(array &$definitions): void {
  // Add a plugin to an existing context.
  if (isset($definitions['eca_base'])) {
    $definitions['eca_base']['components']['element']['plugins'][] = 'my_custom_action';
  }
}

/**
 * Implements hook_modeler_api_dependency_info_alter().
 */
function my_module_modeler_api_dependency_info_alter(array &$definitions): void {
  // Modify dependency rules.
}

/**
 * Implements hook_modeler_api_template_token_info_alter().
 */
function my_module_modeler_api_template_token_info_alter(array &$definitions): void {
  // Modify template token definitions.
}

/**
 * Implements hook_modeler_api_theme_info_alter().
 */
function my_module_modeler_api_theme_info_alter(array &$definitions): void {
  // Add another library to an existing theme.
  if (isset($definitions['dark'])) {
    $definitions['dark']['libraries'][] = 'my_module/dark_extras';
  }
}
```
