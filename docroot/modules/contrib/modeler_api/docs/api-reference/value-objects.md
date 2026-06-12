# Value Objects

The Modeler API uses several value objects to represent model data in a
type-safe, structured way. These objects are passed between Model Owners,
Modelers, and the Api service.

## Component

**Class:** `Drupal\modeler_api\Component`

The primary value object representing a single element in a model. Components
are created by both Model Owners (when reporting used components) and Modelers
(when parsing raw data).

### Constructor

```php
use Drupal\modeler_api\Component;
use Drupal\modeler_api\Api;

$component = new Component(
  owner: $modelOwnerPlugin,
  id: 'Activity_1abc123',
  type: Api::COMPONENT_TYPE_ELEMENT,
  pluginId: 'action_message_action',
  label: 'Show message',
  configuration: ['message' => 'Hello world'],
  successors: [$successor1, $successor2],
  parentId: 'Lane_1',
  color: new ComponentColor('#ffffff', '#000000'),
);
```

### Properties and methods

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Unique component ID within the model |
| `getType()` | `int` | Component type constant |
| `getPluginId()` | `string` | Plugin ID (from the Model Owner's domain) |
| `getLabel()` | `string` | Human-readable label |
| `getConfiguration()` | `array` | Plugin configuration array |
| `getSuccessors()` | `ComponentSuccessor[]` | Outgoing connections |
| `getParentId()` | `?string` | Parent component ID (for swimlane grouping) |
| `setParentId(?string $parentId)` | `void` | Set the parent ID |
| `getColor()` | `?ComponentColor` | Visual color settings |
| `validate()` | `string[]` | Validate the component configuration using the Form API |

### Validation

The `validate()` method uses `RuntimePluginForm` to build and validate the
component's configuration form without actually rendering it:

```php
$errors = $component->validate();
if (!empty($errors)) {
  foreach ($errors as $error) {
    \Drupal::logger('my_module')->warning($error);
  }
}
```

This is used during the save cycle to catch configuration errors before
persisting.

## ComponentSuccessor

**Class:** `Drupal\modeler_api\ComponentSuccessor`

Represents a directed connection from one component to another, optionally
through a condition (link) component.

### Constructor

```php
use Drupal\modeler_api\ComponentSuccessor;

$successor = new ComponentSuccessor(
  id: 'Activity_2def456',       // Target component ID
  conditionId: 'Flow_1ghi789', // Link/condition component ID
);
```

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Target component ID |
| `getConditionId()` | `string` | Link/condition component ID connecting to the target |

## ComponentColor

**Class:** `Drupal\modeler_api\ComponentColor`

Represents the visual color of a component in the modeler canvas.

### Constructor

```php
use Drupal\modeler_api\ComponentColor;

$color = new ComponentColor(
  fill: '#e8f5e9',    // Background fill color
  stroke: '#2e7d32',  // Border/stroke color
);
```

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getFill()` | `string` | CSS fill/background color |
| `getStroke()` | `string` | CSS stroke/border color |

The class is `readonly`, making it immutable after construction.

## Context

**Class:** `Drupal\modeler_api\Context`

Represents a resolved context definition from YAML discovery. See
[Context Plugin Manager](../plugin-managers/context/index.md) for usage
details.

### Constructor

```php
use Drupal\modeler_api\Context;

$context = new Context(
  id: 'eca_form',
  topic: 'Altering Drupal forms',
  modelOwner: 'eca',
  components: ['start' => ['plugins' => ['form:form_build']]],
  provider: 'eca_ng',
  includes: ['eca_base'],
  contextPluginManager: $contextPluginManager,
);
```

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Context ID |
| `getTopic()` | `string` | Human-readable topic |
| `getModelOwner()` | `string` | Model Owner plugin ID |
| `getComponents()` | `array` | Raw component definitions |
| `getProvider()` | `string` | Providing module |
| `getIncludes()` | `string[]` | Direct include IDs |
| `getPlugins($typeName)` | `string[]` | Resolved plugin list (with includes) |
| `hasPlugin($typeName, $pluginId)` | `bool` | Check plugin availability |

## Dependency

**Class:** `Drupal\modeler_api\Dependency`

Represents a set of predecessor constraint rules from YAML discovery. See
[Plugin Dependency Manager](../plugin-managers/plugin-dependency/index.md)
for usage details.

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Dependency set ID |
| `getModelOwner()` | `string` | Model Owner plugin ID |
| `getComponents()` | `array` | Raw component rules |
| `getProvider()` | `string` | Providing module |
| `getRules($typeName)` | `array` | Rules for a component type |
| `hasDependency($typeName, $pluginId)` | `bool` | Has constraints (glob matching) |
| `getRequiredPredecessors($typeName, $pluginId)` | `array` | Required predecessors (glob matching) |

## TemplateToken

**Class:** `Drupal\modeler_api\TemplateToken`

Represents a token tree definition from YAML discovery. See
[Template Token Manager](../plugin-managers/template-token/index.md)
for usage details.

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Token set ID |
| `getModelOwner()` | `string` | Model Owner plugin ID |
| `getTokens()` | `array` | Full token tree |
| `getProvider()` | `string` | Providing module |
| `hasToken($path)` | `bool` | Check token existence by colon-separated path |
| `findToken($path)` | `?array` | Find token entry by colon-separated path |
| `resolvePurpose($path)` | `?string` | Resolve the purpose (`select` or `config`) for a token path. Purpose is inherited from the first-level child under each indicator. |
| `collectSelectors($path)` | `string[]` | Walk the token path and collect all CSS `selector` keys from the purpose node down to the target. |

## DataModel entity

**Class:** `Drupal\modeler_api\Entity\DataModel`

A config entity used for separate storage of model raw data.

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | Composite ID: `{entityType}_{modelerId}_{modelId}` |
| `data` | `string` | Raw model data (XML, JSON, etc.) |

This entity is managed automatically by the `ModelOwnerBase::setModelData()`
and `getModelData()` methods when the storage mode is set to
`STORAGE_OPTION_SEPARATE`. Developers normally do not interact with it
directly.
