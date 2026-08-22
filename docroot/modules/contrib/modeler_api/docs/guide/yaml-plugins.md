# YAML Plugin Definitions

The Modeler API provides three YAML-based plugin types that allow any module to
contribute metadata for Model Owners without writing PHP code. These are ideal
for curating component palettes, defining ordering constraints, and providing
template tokens.

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

## Alter hooks

All three YAML-based plugin types support alter hooks for programmatic
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
```
