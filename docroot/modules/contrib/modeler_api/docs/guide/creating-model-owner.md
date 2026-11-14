# Creating a Model Owner

This guide walks through implementing a Model Owner plugin that integrates your
config entities with the Modeler API's visual modelers.

## Prerequisites

- A Drupal module with an existing config entity type.
- The `modeler_api` module as a dependency.

## Step 1: Create the plugin class

Create a PHP class in your module's `src/Plugin/ModelerApiModelOwner/`
directory:

```php
<?php

namespace Drupal\my_module\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase;

#[ModelOwner(
  id: "my_module_workflow",
  label: new TranslatableMarkup("My Workflow"),
  description: new TranslatableMarkup("Visual modeler for My Module workflows."),
)]
class Workflow extends ModelOwnerBase {

  /**
   * {@inheritdoc}
   */
  public function modelIdExistsCallback(): array {
    return [\Drupal\my_module\Entity\Workflow::class, 'load'];
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityProviderId(): string {
    return 'my_module';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityTypeId(): string {
    return 'my_module_workflow';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityBasePath(): ?string {
    return 'admin/config/workflow/my-workflows';
  }

  /**
   * {@inheritdoc}
   */
  public function supportedOwnerComponentTypes(): array {
    return [
      Api::COMPONENT_TYPE_START => 'trigger',
      Api::COMPONENT_TYPE_ELEMENT => 'action',
      Api::COMPONENT_TYPE_LINK => 'condition',
    ];
  }

  // ... implement remaining abstract methods
}
```

## Step 2: Implement component management

The core of a Model Owner is mapping between the Modeler API's generic
component types and your module's domain-specific plugins.

### `availableOwnerComponents(int $type): array`

Return all available plugins for a component type:

```php
public function availableOwnerComponents(int $type): array {
  return match ($type) {
    Api::COMPONENT_TYPE_START => $this->getEventPlugins(),
    Api::COMPONENT_TYPE_ELEMENT => $this->getActionPlugins(),
    Api::COMPONENT_TYPE_LINK => $this->getConditionPlugins(),
    default => [],
  };
}

protected function getActionPlugins(): array {
  // Use lazy getter injection -- constructor is final.
  if (!isset($this->actionManager)) {
    $this->actionManager = \Drupal::service('plugin.manager.my_action');
  }
  $plugins = [];
  foreach ($this->actionManager->getDefinitions() as $id => $def) {
    $plugins[$id] = $this->actionManager->createInstance($id);
  }
  return $plugins;
}
```

### `ownerComponent(int $type, string $id, array $config): ?PluginInspectionInterface`

Instantiate a specific plugin by type and ID:

```php
public function ownerComponent(int $type, string $id,
  array $config = []): ?PluginInspectionInterface {
  $manager = match ($type) {
    Api::COMPONENT_TYPE_START => $this->getEventManager(),
    Api::COMPONENT_TYPE_ELEMENT => $this->getActionManager(),
    Api::COMPONENT_TYPE_LINK => $this->getConditionManager(),
    default => NULL,
  };
  if ($manager === NULL) {
    return NULL;
  }
  try {
    return $manager->createInstance($id, $config);
  }
  catch (\Exception) {
    return NULL;
  }
}
```

### `ownerComponentId(int $type): string`

Generate a unique ID for a new component:

```php
public function ownerComponentId(int $type): string {
  $prefix = match ($type) {
    Api::COMPONENT_TYPE_START => 'Event',
    Api::COMPONENT_TYPE_ELEMENT => 'Action',
    Api::COMPONENT_TYPE_LINK => 'Condition',
    default => 'Component',
  };
  return $prefix . '_' . $this->uuidGenerator->generate();
}
```

## Step 3: Implement the save cycle

### `usedComponents(ConfigEntityInterface $model): array`

Return the components currently stored in the config entity:

```php
public function usedComponents(ConfigEntityInterface $model): array {
  $components = [];

  // Build start components from the entity's event configuration.
  foreach ($model->get('events') ?? [] as $eventId => $event) {
    $successors = [];
    foreach ($event['successors'] ?? [] as $successor) {
      $successors[] = new \Drupal\modeler_api\ComponentSuccessor(
        $successor['target'],
        $successor['condition'] ?? '',
      );
    }
    $components[] = new Component(
      $this,
      $eventId,
      Api::COMPONENT_TYPE_START,
      $event['plugin'],
      $event['label'] ?? '',
      $event['configuration'] ?? [],
      $successors,
    );
  }

  // Similarly for actions and conditions...

  return $components;
}
```

### `resetComponents(ConfigEntityInterface $model): ModelOwnerInterface`

Clear the entity's component storage before re-adding from the modeler:

```php
public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface {
  $model->set('events', []);
  $model->set('actions', []);
  $model->set('conditions', []);
  return $this;
}
```

### `addComponent(ConfigEntityInterface $model, Component $component): bool`

Add a single component from parsed raw data:

```php
public function addComponent(ConfigEntityInterface $model,
  Component $component): bool {
  $type = $component->getType();
  $id = $component->getId();
  $pluginId = $component->getPluginId();
  $config = $component->getConfiguration();

  $successorData = [];
  foreach ($component->getSuccessors() as $successor) {
    $successorData[] = [
      'target' => $successor->getId(),
      'condition' => $successor->getConditionId(),
    ];
  }

  switch ($type) {
    case Api::COMPONENT_TYPE_START:
      $events = $model->get('events') ?? [];
      $events[$id] = [
        'plugin' => $pluginId,
        'label' => $component->getLabel(),
        'configuration' => $config,
        'successors' => $successorData,
      ];
      $model->set('events', $events);
      return TRUE;

    case Api::COMPONENT_TYPE_ELEMENT:
      // Similar for actions...
      return TRUE;

    case Api::COMPONENT_TYPE_LINK:
      // Similar for conditions...
      return TRUE;
  }

  return FALSE;
}
```

### `buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId, bool $modelIsNew): array`

Build the config form for a component plugin:

```php
public function buildConfigurationForm(PluginInspectionInterface $plugin,
  ?string $modelId = NULL, bool $modelIsNew = TRUE): array {
  if ($plugin instanceof PluginFormInterface) {
    try {
      return $plugin->buildConfigurationForm([], new FormState());
    }
    catch (\Exception $e) {
      return ['error' => ['#markup' => $e->getMessage()]];
    }
  }
  return [];
}
```

## Step 4: Using ComponentWrapperPlugin

If your components are not Drupal plugins (e.g., they are simple configuration
arrays), wrap them using `ComponentWrapperPlugin`:

```php
use Drupal\modeler_api\Plugin\ComponentWrapperPlugin;

public function availableOwnerComponents(int $type): array {
  if ($type === Api::COMPONENT_TYPE_START) {
    return [
      'manual_trigger' => new ComponentWrapperPlugin(
        type: Api::COMPONENT_TYPE_START,
        id: 'manual_trigger',
        configuration: [],
        label: 'Manual Trigger',
      ),
    ];
  }
  return [];
}
```

This pattern is used by the `ai_agents` module for agent sub-processes.

## Step 5: Storage configuration

Override these methods to control how model data is stored:

```php
// Default storage for all models of this owner.
public function defaultStorageMethod(): string {
  // Options: STORAGE_OPTION_THIRD_PARTY, STORAGE_OPTION_SEPARATE,
  // STORAGE_OPTION_NONE
  return Settings::STORAGE_OPTION_THIRD_PARTY;
}

// Prevent users from changing the storage method.
public function enforceDefaultStorageMethod(): bool {
  return FALSE; // TRUE to lock the storage method
}
```

!!! note "AI Agents pattern"
    The `ai_agents` module uses `STORAGE_OPTION_NONE` with
    `enforceDefaultStorageMethod()` returning `TRUE`, because AI agent
    configuration is fully captured in the agent config entity itself -- no
    separate raw data storage is needed.

## Step 6: Model constraints

Override `modelConstraints()` to declare cardinality rules for component types.
These constraints are validated server-side during save and delivered to the
frontend for client-side enforcement.

```php
use Drupal\modeler_api\Api;

public function modelConstraints(): array {
  return [
    // Exactly one start component, with exactly one successor.
    Api::COMPONENT_TYPE_START => [
      'min' => 1,
      'max' => 1,
      'successors' => ['min' => 1, 'max' => 1],
    ],
    // At least one element, no limit on how many.
    Api::COMPONENT_TYPE_ELEMENT => [
      'min' => 1,
    ],
    // Links must not have more than one successor.
    Api::COMPONENT_TYPE_LINK => [
      'successors' => ['max' => 1],
    ],
  ];
}
```

All keys (`min`, `max`, `successors`, `successors.min`, `successors.max`) are
optional. Omit a key to leave that dimension unconstrained. Return an empty
array (the default) to impose no constraints at all.

When constraints are declared, also implement `componentLabelsPlural()` so
that validation error messages read naturally:

```php
public function componentLabels(): array {
  return [
    'start' => 'Trigger',
    'element' => 'Action',
    'link' => 'Condition',
  ];
}

public function componentLabelsPlural(): array {
  return [
    'start' => 'Triggers',
    'element' => 'Actions',
    'link' => 'Conditions',
  ];
}
```

See the [Model Owner plugin manager](../plugin-managers/model-owner/index.md#model-constraints)
documentation for the full constraint key reference and the error messages
produced by validation.

## Step 7: Optional features

### Replay data

If your model supports execution replay/debugging:

```php
public function supportsReplayData(): bool {
  return TRUE;
}

public function getReplayData(string $hash): array {
  // Return execution trace data for the given hash.
  return $this->getReplayService()->load($hash);
}

public function getReplayDataByComponent(string $modelId,
  string $componentId): array {
  return $this->getReplayService()->loadByComponent($modelId, $componentId);
}
```

### Testing

If your model supports in-modeler testing:

```php
public function supportsTesting(): bool {
  return TRUE;
}

public function startTestJob(string $modelId,
  string $componentId): string|TranslatableMarkup {
  // Start an async test and return a job ID.
  return $this->getTestRunner()->start($modelId, $componentId);
}

public function pollTestJob(string $jobId): array|null|TranslatableMarkup {
  // NULL = still running, array = results, TranslatableMarkup = error.
  return $this->getTestRunner()->poll($jobId);
}
```

### Documentation links

Provide links to external documentation for each component plugin:

```php
public function docBaseUrl(): ?string {
  return 'https://docs.my-module.org/plugins';
}

public function pluginDocUrl(PluginInspectionInterface $plugin,
  string $pluginType): ?string {
  $base = $this->docBaseUrl();
  if ($base === NULL) {
    return NULL;
  }
  return $base . '/' . $pluginType . '/' . $plugin->getPluginId();
}
```

## Complete minimal example

See the [`ai_agents` module](https://www.drupal.org/project/ai_agents) for a
real-world Model Owner implementation, or the ECA module's `eca_ui` submodule
for a full-featured implementation with status, templates, testing, and replay
support.
