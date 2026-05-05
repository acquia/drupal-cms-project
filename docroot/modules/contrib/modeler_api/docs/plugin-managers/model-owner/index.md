# Model Owner Plugin Manager

The Model Owner plugin manager discovers and manages plugins that own the
configuration entities being modeled. A Model Owner defines what components
exist, how they map to Drupal plugins, and how models are stored and
manipulated.

## Plugin manager details

| Property | Value |
|----------|-------|
| **Service ID** | `plugin.manager.modeler_api.model_owner` |
| **Class** | `Drupal\modeler_api\Plugin\ModelOwnerPluginManager` |
| **Discovery** | PHP attribute (`#[ModelOwner]`) |
| **Plugin namespace** | `Plugin\ModelerApiModelOwner` |
| **Interface** | `Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface` |
| **Base class** | `Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase` |
| **Attribute** | `Drupal\modeler_api\Attribute\ModelOwner` |
| **Alter hook** | `hook_modeler_api_model_owner_info_alter()` |
| **Cache tag** | `modeler_api_model_owner_plugins` |

## Attribute definition

The `#[ModelOwner]` attribute accepts the following parameters:

```php
#[ModelOwner(
  id: "my_owner",
  label: new TranslatableMarkup("My Owner"),
  description: new TranslatableMarkup("Manages my config entities."),
  uiLabelNewModel: new TranslatableMarkup("Add new model"),
  uiLabelNewModelWithModeler: new TranslatableMarkup("Add new model with modeler"),
)]
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | `string` | Yes | Unique plugin ID |
| `label` | `TranslatableMarkup` | No | Human-readable name |
| `description` | `TranslatableMarkup` | No | Brief description |
| `uiLabelNewModel` | `TranslatableMarkup` | No | Label for the "Add" button |
| `uiLabelNewModelWithModeler` | `TranslatableMarkup` | No | Label for "Add with modeler" button |

## ModelOwnerInterface

The interface defines the full contract a Model Owner must fulfill. Methods are
grouped by responsibility:

### Identity and entity mapping

| Method | Return | Description |
|--------|--------|-------------|
| `label()` | `string` | Plugin label |
| `description()` | `string` | Plugin description |
| `componentLabels()` | `array` | Human-readable singular labels for supported component types (e.g. `['start' => 'Event', 'element' => 'Action']`) |
| `componentLabelsPlural()` | `array` | Human-readable plural labels for supported component types (e.g. `['start' => 'Events', 'element' => 'Actions']`). Used for grouping headings in the component panel, quick-add popups, and constraint validation error messages. |
| `modelIdExistsCallback()` | `array` | Callback to check if a model ID already exists (e.g. `[MyEntity::class, 'load']`) |
| `configEntityProviderId()` | `string` | Module name providing the config entity type |
| `configEntityTypeId()` | `string` | Config entity type ID (e.g. `eca`) |
| `configEntityBasePath()` | `?string` | Admin base path without leading/trailing slash, or `NULL` if routing is self-managed |

### Settings and form customization

| Method | Return | Description |
|--------|--------|-------------|
| `settingsForm()` | `?string` | FQCN of a settings form class, or `NULL` |
| `modelConfigFormAlter(array &$form)` | `void` | Alter the default model metadata config form |

### Model lifecycle

| Method | Return | Description |
|--------|--------|-------------|
| `isEditable($model)` | `bool` | Whether the model can be edited |
| `isExportable($model)` | `bool` | Whether the model can be exported |
| `enable($model)` | `void` | Enable the model (final in base) |
| `disable($model)` | `void` | Disable the model (final in base) |
| `clone($model)` | `ConfigEntityInterface` | Clone the model (final in base) |
| `export($model)` | `Response` | Export as `.tar.gz` archive (final in base) |

### Metadata accessors

All metadata methods follow a `get`/`set` pattern and are declared `final` in
the base class. They store values in the config entity's third-party settings
under the `modeler_api` namespace.

| Property | Getter | Setter |
|----------|--------|--------|
| Label | `getLabel($model)` | `setLabel($model, $label)` |
| Status | `getStatus($model)` | `setStatus($model, $status)` |
| Version | `getVersion($model)` | `setVersion($model, $version)` |
| Template | `getTemplate($model)` | `setTemplate($model, $template)` |
| Storage | `getStorage($model)` | `setStorage($model, $storage)` |
| Documentation | `getDocumentation($model)` | `setDocumentation($model, $doc)` |
| Tags | `getTags($model)` | `setTags($model, $tags)` |
| Changelog | `getChangelog($model)` | `setChangelog($model, $log)` |
| Annotations | `getAnnotations($model)` | `setAnnotations($model, $annotations)` |
| Colors | `getColors($model)` | `setColors($model, $colors)` |
| Swimlanes | `getSwimlanes($model)` | `setSwimlanes($model, $swimlanes)` |
| Model data | `getModelData($model)` | `setModelData($model, $data)` |
| Modeler ID | `getModelerId($model)` | `setModelerId($model, $id)` |

### Component management

These methods define how the Model Owner maps its domain concepts to the
generic component type system.

| Method | Return | Description |
|--------|--------|-------------|
| `supportedOwnerComponentTypes()` | `array` | Map of `COMPONENT_TYPE_*` constants to domain-specific names |
| `modelConstraints()` | `array` | Cardinality constraints for component types and their successors. See [Model constraints](#model-constraints) below. |
| `availableOwnerComponents($type)` | `PluginInspectionInterface[]` | All available plugins for a component type |
| `favoriteOwnerComponents()` | `array` | Preferred plugin IDs grouped by type |
| `componentLabels()` | `array` | Human-readable labels for supported component types (e.g. `['start' => 'Event', 'element' => 'Action']`) |
| `ownerComponentId($type)` | `string` | Generate a new component ID for a type |
| `ownerComponentDefaultConfig($type, $id)` | `array` | Default configuration for a component |
| `ownerComponent($type, $id, $config)` | `?PluginInspectionInterface` | Instantiate a component plugin |
| `ownerComponentEditable($plugin)` | `bool` | Whether a component's config can be edited in the UI |
| `ownerComponentPluginChangeable($plugin)` | `bool` | Whether a component's plugin type can be swapped |
| `buildConfigurationForm($plugin, $modelId, $modelIsNew)` | `array` | Build the config form for a component plugin |
| `skipConfigurationValidation($type, $id)` | `bool` | Skip validation for specific components |

### Save cycle methods

These are called by the Api service during model save:

| Method | Return | Description |
|--------|--------|-------------|
| `usedComponents($model)` | `Component[]` | Return all components currently in the model (implement this) |
| `getUsedComponents($model)` | `Component[]` | Public accessor that also adds swimlane components (final, call this) |
| `resetComponents($model)` | `$this` | Clear all components before re-adding from raw data |
| `addComponent($model, $component)` | `bool` | Add a single component to the model |
| `finalizeAddingComponents($model)` | `void` | Called after all components are added successfully |
| `updateComponent($model, $component)` | `bool` | Update a single existing component |
| `usedComponentsInfo($model)` | `string[]` | Summary strings about used components |

### Storage

| Method | Return | Description |
|--------|--------|-------------|
| `storageMethod($model)` | `?string` | Resolved storage method for this model |
| `storageId($model)` | `string` | Unique ID for separate storage entities |
| `defaultStorageMethod()` | `string` | Default storage method for this owner |
| `enforceDefaultStorageMethod()` | `bool` | Whether the user can change the storage method |

### Template support

| Method | Return | Default | Description |
|--------|--------|---------|-------------|
| `supportsTemplate()` | `bool` | auto-detected | Whether the entity type has a `template` key |
| `applyTemplate($templateId, $componentId, $target, $hiddenConfig, $config)` | `void` | no-op | Apply a template to a model, creating a new model if it does not exist |

The `applyTemplate()` method is called by the `Templates` controller when a
user applies a template token. It receives the template model ID, the component
ID within that template, the CSS selector target, and optional hidden/visible
configuration values.

### Optional features

| Method | Return | Default | Description |
|--------|--------|---------|-------------|
| `supportsStatus()` | `bool` | auto-detected | Whether the entity type has a `status` key |
| `supportsReplayData()` | `bool` | `false` | Whether replay/debug data is available |
| `getReplayData($hash)` | `array` | `[]` | Replay data for a hash |
| `getReplayDataByComponent($modelId, $componentId)` | `array` | `[]` | Replay data for a specific component |
| `supportsTesting()` | `bool` | `false` | Whether in-modeler testing is supported |
| `startTestJob($modelId, $componentId)` | `string\|TranslatableMarkup` | error message | Start a test job |
| `pollTestJob($jobId)` | `array\|null\|TranslatableMarkup` | error message | Poll test job status |

### Documentation support

| Method | Return | Description |
|--------|--------|-------------|
| `docBaseUrl()` | `?string` | Base URL for external documentation |
| `pluginDocUrl($plugin, $pluginType)` | `?string` | URL for a specific plugin's documentation |
| `prepareFormFieldForValidation(&$value, &$replacement, $element)` | `?string` | Pre-validation hook for form fields |

## Model constraints

Model Owners can declare cardinality constraints on component types via
`modelConstraints()`. These constraints control both the number of components
of a given type allowed in a model and the number of outgoing connections
(successors) each component of that type may have.

Constraints are enforced in two places:

- **Server-side** -- during the save cycle, the `Api` service calls
  `validateModelConstraints()` and adds error messages for any violations.
- **Client-side** -- constraints are delivered to the frontend via
  `drupalSettings.modeler_api.model_constraints` so that the modeler UI can
  prevent violations before the save request is sent.

### Return format

`modelConstraints()` returns an associative array keyed by `COMPONENT_TYPE_*`
constants. Each value is an array with optional `min` and `max` keys for the
component count, and an optional `successors` key with its own `min`/`max` for
the number of outgoing connections per component of that type.

```php
public function modelConstraints(): array {
  return [
    Api::COMPONENT_TYPE_START => [
      'min' => 1,
      'max' => 1,
      'successors' => ['min' => 1, 'max' => 1],
    ],
    Api::COMPONENT_TYPE_ELEMENT => [
      'min' => 1,
      'successors' => ['max' => 0],
    ],
    Api::COMPONENT_TYPE_GATEWAY => [
      'successors' => ['min' => 2],
    ],
  ];
}
```

### Constraint keys

| Key | Type | Description |
|-----|------|-------------|
| `min` | `int` | Minimum number of components of this type required in the model |
| `max` | `int` | Maximum number of components of this type allowed in the model |
| `successors` | `array` | Nested constraint for outgoing connections per component |
| `successors.min` | `int` | Minimum number of successors each component of this type must have |
| `successors.max` | `int` | Maximum number of successors each component of this type may have (`0` = no successors allowed) |

All keys are optional. Omitting a key means "no constraint" for that dimension.
Returning an empty array (the default in `ModelOwnerBase`) means no constraints
at all.

### Validation error messages

The `Api` service generates human-readable, translatable error messages for
constraint violations. The messages use singular labels from
`componentLabels()` and plural labels from `componentLabelsPlural()` depending
on the constraint value:

- `"A model requires at least one @label."` (when `min` is 1)
- `"A model requires at least @min @label_plural."` (when `min` > 1)
- `"A model allows at most one @label."` (when `max` is 1)
- `"A model allows at most @max @label_plural."` (when `max` > 1)
- `"@label "@name" requires at least @min successor(s)."` (successor min)
- `"@label "@name" must not have any successors."` (when successor `max` is 0)
- `"@label "@name" allows at most @max successor(s)."` (successor max > 0)

## ComponentWrapperPlugin

When a Model Owner's components are not Drupal plugins themselves (e.g. AI
Agent sub-agents), they can be wrapped using `ComponentWrapperPlugin`:

```php
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ComponentWrapperPlugin;

$wrapped = new ComponentWrapperPlugin(
  type: Api::COMPONENT_TYPE_SUBPROCESS,
  id: 'my_subprocess_id',
  configuration: ['key' => 'value'],
  label: 'My Subprocess',
);
```

This implements `ComponentWrapperPluginInterface` (which extends
`ConfigurableInterface`) and provides a `getType()` method.

## Existing implementations

### AI Agents (`ai_agents_agent`)

The `ai_agents` module implements a Model Owner for AI agent configuration:

- **Config entity type:** `ai_agent`
- **Supported types:** `START` (agent metadata), `SUBPROCESS` (sub-agents),
  `ELEMENT` (function call tools), `LINK` (connections)
- **Storage:** `STORAGE_OPTION_NONE` (enforced -- agents don't store raw data)
- Uses `ComponentWrapperPlugin` for sub-agents and start components
- Function call tools are actual Drupal plugins from the AI module

### ECA (`eca`)

The `eca` module (via `eca_ui` submodule) provides a Model Owner for ECA
workflow configuration:

- **Config entity type:** `eca`
- **Supported types:** `START` (events), `ELEMENT` (actions), `LINK`
  (conditions), `GATEWAY` (gateways)
- Supports status, templates, replay data, and testing
