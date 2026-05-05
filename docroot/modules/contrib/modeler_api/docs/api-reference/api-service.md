# Api Service

The `Api` class is the central service of the Modeler API module. It
coordinates interactions between Model Owners, Modelers, and the three
YAML-based plugin systems.

## Service details

| Property | Value |
|----------|-------|
| **Service ID** | `modeler_api.service` |
| **Class** | `Drupal\modeler_api\Api` |
| **Autowire alias** | `Drupal\modeler_api\Api` |

## Component type constants

The `Api` class defines the component type system used throughout the module:

```php
use Drupal\modeler_api\Api;

Api::COMPONENT_TYPE_START;      // 1
Api::COMPONENT_TYPE_SUBPROCESS; // 2
Api::COMPONENT_TYPE_SWIMLANE;   // 3
Api::COMPONENT_TYPE_ELEMENT;    // 4
Api::COMPONENT_TYPE_LINK;       // 5
Api::COMPONENT_TYPE_GATEWAY;    // 6
Api::COMPONENT_TYPE_ANNOTATION; // 7
```

### Type name mapping

The `Api::COMPONENT_TYPE_NAMES` constant maps integer types to string names:

```php
Api::COMPONENT_TYPE_NAMES = [
  1 => 'start',
  2 => 'subprocess',
  3 => 'swimlane',
  4 => 'element',
  5 => 'link',
  6 => 'gateway',
  7 => 'annotation',
];
```

## Key methods

### `findOwner(ConfigEntityInterface $model): ?ModelOwnerInterface`

Finds the Model Owner plugin responsible for a given config entity by matching
the entity type ID against all registered Model Owner plugins.

```php
$api = \Drupal::service('modeler_api.service');
$owner = $api->findOwner($ecaEntity);
// Returns the 'eca' ModelOwner plugin instance.
```

### `prepareModelFromData(string $data, string $ownerId, string $modelerId, bool $isNew, bool $dryRun = FALSE): ?ConfigEntityInterface`

The core save method. Parses raw model data using the Modeler, extracts
metadata, and populates the Model Owner's config entity with components.

**Steps performed:**

1. Instantiate the Model Owner and Modeler plugins.
2. Call `ModelerInterface::parseData()` to parse the raw data.
3. Extract metadata (ID, label, status, version, tags, etc.) from the Modeler.
4. Load or create the config entity.
5. Set metadata on the entity via the Model Owner.
6. Call `ModelOwnerInterface::resetComponents()`.
7. Iterate over `ModelerInterface::readComponents()` and call
   `ModelOwnerInterface::addComponent()` for each.
8. Validate components using `Component::validate()`.
9. If not dry run: call `ModelOwnerInterface::finalizeAddingComponents()`.
10. Store raw data via `ModelOwnerInterface::setModelData()`.
11. Save the config entity.

```php
$model = $api->prepareModelFromData(
  $xmlData,
  'eca',
  'bpmn_io',
  isNew: FALSE,
);
```

### `embedIntoForm(array &$form, ModelOwnerInterface $owner, ModelerInterface $modeler, string $id, string $data, bool $isNew, bool $readOnly): void`

Embeds the modeler's editing UI into a Drupal form. Adds the model config form
(metadata fields) and the modeler's canvas render array. Also attaches
context, dependency, and template token data as JavaScript settings.

### `edit(ModelOwnerInterface $owner, ModelerInterface $modeler, string $modelId, string $data, bool $isNew, bool $readOnly): array`

Returns the complete render array for editing a model. Wraps
`embedIntoForm()` in the `modeler_api_wrapper` form.

### `view(ModelOwnerInterface $owner, ModelerInterface $modeler, ConfigEntityInterface $model): array`

Returns a read-only render array for viewing a model.

### `exportArchive(ModelOwnerInterface $owner, ConfigEntityInterface $model, string $filename): void`

Creates a `.tar.gz` archive containing:

- The raw model data file (with appropriate extension from the Modeler)
- A YAML metadata file with model settings

### `availableOwnerComponents(ModelOwnerInterface $owner, int $type): array`

Returns all available plugins for a Model Owner for a given component type.
Delegates to `ModelOwnerInterface::availableOwnerComponents()`.

### `getContexts(ModelOwnerInterface $owner): array`

Returns the resolved context list for a Model Owner via the
`ContextListBuilder`.

### `getDependencies(ModelOwnerInterface $owner): array`

Returns the merged dependency rules for a Model Owner via the
`DependencyListBuilder`.

### `getNestedDependencies(ModelOwnerInterface $owner, string $modelId): array`

For models that contain subprocesses referencing other models, this method
recursively collects dependency data from nested models.

### `editUrl(string $type, string $id): Url`

Returns the edit URL for an entity of the given type and ID. Tries the
`entity.{type}.edit` route first, falling back to `entity.{type}.edit_form`,
and ultimately the collection route.

### `prepareGlobalTokens(): array`

Prepares the Drupal global token tree (from the `token` module, if available)
for use in the modeler's JavaScript UI.

### `prepareTemplateTokens(ModelOwnerInterface $owner): array`

Prepares the merged template token tree for a Model Owner via the
`TemplateTokenListBuilder`. Only returns data if the Model Owner supports
templates.

### `getModeler(): ?ModelerInterface`

Returns the sole non-fallback Modeler plugin instance, if exactly one Modeler
(besides the built-in fallback) is available. Returns `NULL` if zero or
multiple modelers are installed.

### `prepareModelConstraints(ModelOwnerInterface $owner): array`

Translates the model owner's cardinality constraints from integer component
type keys to string names (e.g. `'start'`, `'element'`, `'gateway'`) that the
frontend understands. The result is attached to
`drupalSettings.modeler_api.model_constraints`.

Returns an empty array if the owner declares no constraints.

### `validateModelConstraints(ModelOwnerInterface $owner, array $componentTypeCounts, array $successorCountsByType): void`

Validates model-level cardinality constraints during the save cycle. Called
internally by `prepareModelFromData()` after all components have been parsed.

**Parameters:**

- `$componentTypeCounts` -- an array keyed by component type constant with
  the total count of components of that type in the model.
- `$successorCountsByType` -- a per-type list of component successor info,
  where each entry contains `id`, `label`, and `count` for a component.

For each constraint declared by the Model Owner's `modelConstraints()`, this
method checks both component counts and per-component successor counts. Any
violations are appended to the internal error list, retrievable via
`getErrors()`.

### `getErrors(): array`

Returns error messages collected during the last `prepareModelFromData()` call.
This includes any violations from model constraint validation.

## drupalSettings output

When a modeler UI is rendered via `edit()` or `view()`, the following data is
attached to `drupalSettings.modeler_api`:

| Key | Type | Description |
|-----|------|-------------|
| `metadata` | `object` | Model metadata (version, label, description, storage, executable, template, tags, changelog) |
| `component_labels` | `object` | Human-readable singular labels for each component type, from `ModelOwnerInterface::componentLabels()` |
| `component_labels_plural` | `object` | Human-readable plural labels for each component type, from `ModelOwnerInterface::componentLabelsPlural()` |
| `model_constraints` | `object` | Cardinality constraints for component types keyed by type name string (e.g. `start`, `element`). Each entry may contain `min`, `max`, and `successors` (with its own `min`/`max`). From `ModelOwnerInterface::modelConstraints()`. |
| `permissions` | `object` | Current user's permissions for this modeler |
| `favorite_components` | `array` | Preferred component plugin IDs from the Model Owner |
| `global_tokens` | `object` | Global Drupal token tree (requires `drupal/token`) |
| `template_tokens` | `object` | Resolved template token tree for the Model Owner |
| `contexts` | `array` | Context list for the Model Owner |
| `dependencies` | `array` | Dependency rules for the Model Owner |
| `readOnly` | `bool` | Whether the model is in read-only mode |
| `isNew` | `bool` | Whether the model is new |
| `mode` | `string` | Either `edit` or `view` |
| `save_url` | `string` | Save endpoint URL (when basePath is set) |
| `token_url` | `string` | CSRF token URL (when basePath is set) |
| `collection_url` | `string` | Model listing URL (when basePath is set) |
| `config_url` | `string` | Component config form endpoint URL |
| `replay_url` | `string` | Replay data endpoint (if owner supports replay) |
| `test_url` | `string` | Test endpoint (if owner supports testing) |
| `export_url` | `string` | Export archive URL (if model is exportable) |
| `export_recipe_url` | `string` | Export as recipe URL (if model is exportable) |

## Constructor dependencies

The `Api` service receives the following dependencies via the service container:

| Dependency | Service ID |
|-----------|-----------|
| Current user | `current_user` |
| Model Owner plugin manager | `plugin.manager.modeler_api.model_owner` |
| Modeler plugin manager | `plugin.manager.modeler_api.modeler` |
| Config factory | `config.factory` |
| Config storage (export) | `config.storage.export` |
| File system | `file_system` |
| Entity type manager | `entity_type.manager` |
| Route provider | `router.route_provider` |
| Menu link manager | `plugin.manager.menu.link` |
| Context plugin manager | `plugin.manager.modeler_api.context` |
| Dependency plugin manager | `plugin.manager.modeler_api.dependency` |
| Template token plugin manager | `plugin.manager.modeler_api.template_token` |
| Context list builder | `modeler_api.context_list_builder` |
| Dependency list builder | `modeler_api.dependency_list_builder` |
| Template token list builder | `modeler_api.template_token_list_builder` |
| Token service (optional, lazy) | `token` |
| Token tree builder (optional, lazy) | `token.tree_builder` |
