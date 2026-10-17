# Plugin Dependency Manager

The Plugin Dependency manager discovers YAML-based plugins that define
predecessor constraints between components. Dependencies restrict which
components can appear as successors of other components, enabling the modeler
UI to filter available options based on the current context in the model graph.

## Plugin manager details

| Property | Value |
|----------|-------|
| **Service ID** | `plugin.manager.modeler_api.dependency` |
| **Class** | `Drupal\modeler_api\Plugin\DependencyPluginManager` |
| **Discovery** | YAML file (`MODULE.modeler_api.dependencies.yml`) |
| **Value object** | `Drupal\modeler_api\Dependency` |
| **Alter hook** | `hook_modeler_api_dependency_info_alter()` |
| **Cache tag** | `modeler_api_dependency_plugins` |

## YAML file structure

Create a file named `my_module.modeler_api.dependencies.yml` in your module's
root directory:

```yaml
my_dependency_set:
  model_owner: my_owner_plugin_id
  components:
    link:
      plugin_id_pattern:
        - type: start
          id: predecessor_plugin_id
    element:
      another_plugin:
        - type: start
          id: some_event_plugin
        - type: element
          id: some_action_plugin
```

### Top-level keys

Each top-level key defines a dependency set. Multiple sets can be defined in a
single file.

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `model_owner` | `string` | Yes | Plugin ID of the Model Owner |
| `components` | `object` | Yes | Dependency rules per component type |

### Component rules

Under `components`, use the component type names as keys (`start`, `subprocess`,
`swimlane`, `element`, `link`, `gateway`, `annotation`).

Within each component type, keys are **plugin ID patterns** and values are
arrays of **predecessor definitions**:

```yaml
components:
  link:
    eca_form_field_value:          # Plugin that is constrained
      - type: start                # Must be a successor of...
        id: form:form_build        # ...this specific start plugin
      - type: start
        id: form:form_validate
```

Each predecessor definition has:

| Key | Type | Description |
|-----|------|-------------|
| `type` | `string` | Component type name of the required predecessor |
| `id` | `string` | Plugin ID pattern of the required predecessor |

### Glob-style wildcards

Both plugin ID keys and predecessor ID values support **glob-style wildcards**
using `*`:

```yaml
components:
  element:
    eca_form_*:                    # Matches any plugin starting with eca_form_
      - type: start
        id: form:*                 # Matches any form event
```

This means: any element plugin matching `eca_form_*` can only be used as a
successor (directly or indirectly) of a start plugin matching `form:*`.

## Dependency value object

The `Drupal\modeler_api\Dependency` class is a readonly value object:

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Dependency set ID |
| `getModelOwner()` | `string` | Model Owner plugin ID |
| `getComponents()` | `array` | Raw component rules |
| `getProvider()` | `string` | Module that defined this dependency |
| `getRules($typeName)` | `array` | Rules for a specific component type |
| `hasDependency($typeName, $pluginId)` | `bool` | Whether a plugin has any dependency rules |
| `getRequiredPredecessors($typeName, $pluginId)` | `array` | Required predecessors for a plugin |

### Wildcard matching

The `hasDependency()` and `getRequiredPredecessors()` methods perform
glob-style matching using `fnmatch()`:

```php
$dependency = $dependencyManager->getDependency('eca');

// Check if a specific plugin has dependency rules.
$dependency->hasDependency('element', 'eca_form_add_textfield'); // true

// Get required predecessors.
$predecessors = $dependency->getRequiredPredecessors('element', 'eca_form_add_textfield');
// [['type' => 'start', 'id' => 'form:form_build']]
```

## DependencyPluginManager API

| Method | Return | Description |
|--------|--------|-------------|
| `getAllDependencies($reload)` | `Dependency[]` | All discovered dependencies |
| `getDependency($id)` | `?Dependency` | A single dependency set by ID |
| `getDependenciesByModelOwner($modelOwnerId)` | `Dependency[]` | All dependencies for a Model Owner |

## DependencyListBuilder

The `modeler_api.dependency_list_builder` service merges all dependency rules
for a given Model Owner into a single flat structure. The output conforms to
`dependency_list.schema.json`.

```php
/** @var \Drupal\modeler_api\DependencyListBuilder $listBuilder */
$listBuilder = \Drupal::service('modeler_api.dependency_list_builder');
$mergedRules = $listBuilder->build('eca');
```

### JSON schema

The merged output follows `config/schema/dependency_list.schema.json`:

```json
{
  "link": {
    "eca_form_field_value": [
      {"type": "start", "id": "form:form_build"},
      {"type": "start", "id": "form:form_submit"},
      {"type": "start", "id": "form:form_validate"}
    ],
    "eca_route_match": [
      {"type": "start", "id": "kernel:controller"}
    ]
  },
  "element": {
    "eca_form_add_textfield": [
      {"type": "start", "id": "form:form_build"}
    ]
  }
}
```

## Complete example

From the `eca_ng` module (`eca_ng.modeler_api.dependencies.yml`):

```yaml
eca:
  model_owner: eca
  components:
    link:
      eca_route_match:
        - type: start
          id: kernel:controller
      eca_form_operation:
        - type: start
          id: form:form_submit
      eca_form_field_value:
        - type: start
          id: form:form_build
        - type: start
          id: form:form_submit
        - type: start
          id: form:form_validate
    element:
      eca_token_load_route_param:
        - type: start
          id: kernel:controller
      eca_form_add_ajax:
        - type: start
          id: form:form_build
      eca_form_build_entity:
        - type: start
          id: form:form_submit
        - type: start
          id: form:form_validate
```

This file defines that:

- The `eca_route_match` condition can only be used in models triggered by
  `kernel:controller` events.
- The `eca_form_field_value` condition requires a form event as the start
  component.
- The `eca_form_add_ajax` action only works in `form:form_build` models.
- The `eca_form_build_entity` action requires either `form:form_submit` or
  `form:form_validate` as the starting event.
