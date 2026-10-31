# Context Plugin Manager

The Context plugin manager discovers YAML-based plugins that define which
components are available for a specific use case within a Model Owner. Contexts
allow modules to curate focused component palettes rather than exposing every
available plugin at once.

## Plugin manager details

| Property | Value |
|----------|-------|
| **Service ID** | `plugin.manager.modeler_api.context` |
| **Class** | `Drupal\modeler_api\Plugin\ContextPluginManager` |
| **Discovery** | YAML file (`MODULE.modeler_api.contexts.yml`) |
| **Value object** | `Drupal\modeler_api\Context` |
| **Alter hook** | `hook_modeler_api_context_info_alter()` |
| **Cache tag** | `modeler_api_context_plugins` |

## YAML file structure

Create a file named `my_module.modeler_api.contexts.yml` in your module's root
directory:

```yaml
my_context_id:
  topic: 'Human-readable context name'
  model_owner: my_owner_plugin_id
  includes:
    - another_context_id
  components:
    start:
      plugins:
        - plugin_id_1
        - plugin_id_2
    element:
      plugins:
        - plugin_id_3
    link:
      plugins:
        - plugin_id_4
```

### Top-level keys

Each top-level key in the YAML file defines a context. Multiple contexts can
be defined in a single file.

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `topic` | `string` | Yes | Human-readable name for the context (translatable) |
| `model_owner` | `string` | Yes | Plugin ID of the Model Owner this context belongs to |
| `includes` | `string[]` | No | List of other context IDs to include (transitive) |
| `components` | `object` | No | Component type definitions |

### Component type keys

Under `components`, use the component type names as keys:

- `start`
- `subprocess`
- `swimlane`
- `element`
- `link`
- `gateway`
- `annotation`

Each component type contains a `plugins` array listing the plugin IDs that are
available in this context.

## Include mechanism

Contexts can include other contexts via the `includes` key. Includes are
**transitive** -- if context A includes context B, and B includes C, then A
effectively includes all plugins from both B and C.

The `Context::getPlugins()` method resolves includes at runtime by recursively
walking the include chain via the `ContextPluginManager`.

```yaml
# Base context with common plugins.
eca_base:
  topic: 'Commonly used ECA components'
  model_owner: eca
  components:
    start:
      plugins:
        - kernel:controller
    element:
      plugins:
        - action_message_action
        - eca_token_set_value

# Extended context that adds form-specific plugins.
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

In this example, `eca_form` includes all plugins from `eca_base` plus its own
form-specific plugins.

## Context value object

The `Drupal\modeler_api\Context` class is a readonly value object:

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Context ID |
| `getTopic()` | `string` | Human-readable topic |
| `getModelOwner()` | `string` | Model Owner plugin ID |
| `getComponents()` | `array` | Raw component definitions |
| `getProvider()` | `string` | Module that defined this context |
| `getIncludes()` | `string[]` | Direct include IDs |
| `getPlugins($typeName)` | `string[]` | **Resolved** plugin list for a type (includes resolved) |
| `hasPlugin($typeName, $pluginId)` | `bool` | Check if a plugin is available in this context |

### Resolving plugins

The `getPlugins()` method merges the context's own plugins with all included
contexts' plugins (recursively):

```php
$context = $contextManager->getContext('eca_form');

// Returns all 'start' plugins from eca_form AND eca_base.
$startPlugins = $context->getPlugins('start');
// ['form:form_build', 'form:form_submit', 'form:form_validate', 'kernel:controller']

// Check availability.
$context->hasPlugin('element', 'eca_token_set_value'); // true (from eca_base)
```

## ContextPluginManager API

| Method | Return | Description |
|--------|--------|-------------|
| `getAllContexts($reload)` | `Context[]` | All discovered contexts |
| `getContext($id)` | `?Context` | A single context by ID |
| `getContextsByModelOwner($modelOwnerId)` | `Context[]` | All contexts for a Model Owner |

## ContextListBuilder

The `modeler_api.context_list_builder` service merges and resolves all contexts
for a given Model Owner into a flat list suitable for API responses. It outputs
data conforming to the `context_list.schema.json` JSON schema.

```php
/** @var \Drupal\modeler_api\ContextListBuilder $listBuilder */
$listBuilder = \Drupal::service('modeler_api.context_list_builder');
$resolvedList = $listBuilder->build('eca');
```

### JSON schema

The resolved output follows `config/schema/context_list.schema.json`:

```json
[
  {
    "id": "eca_form",
    "topic": "Altering Drupal forms",
    "model_owner": "eca",
    "components": {
      "start": {
        "plugins": ["kernel:controller", "form:form_build", "form:form_submit", "form:form_validate"]
      },
      "element": {
        "plugins": ["action_message_action", "eca_token_set_value", "eca_form_add_textfield", "..."]
      },
      "link": {
        "plugins": ["eca_form_field_value", "eca_form_field_exists"]
      }
    }
  }
]
```

All `includes` are fully resolved -- the output contains the complete merged
plugin lists.
