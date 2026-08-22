# Creating a Modeler

This guide walks through implementing a Modeler plugin that provides a visual
editing experience for models. A Modeler is responsible for rendering the
editing canvas, parsing raw model data, and serializing changes back.

## Prerequisites

- A Drupal module with JavaScript/CSS for the modeler UI.
- The `modeler_api` module as a dependency.

## Step 1: Create the plugin class

Create a PHP class in your module's `src/Plugin/ModelerApiModeler/` directory:

```php
<?php

namespace Drupal\my_modeler\Plugin\ModelerApiModeler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Modeler(
  id: "my_modeler",
  label: new TranslatableMarkup("My Modeler"),
  description: new TranslatableMarkup("A visual modeler using my technology."),
)]
class MyModeler extends ModelerBase {

  /**
   * Internal parsed state.
   */
  protected array $parsedData = [];

  /**
   * {@inheritdoc}
   */
  public function getRawFileExtension(): ?string {
    return 'json';
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(): bool {
    return TRUE;
  }

  // ... implement remaining methods
}
```

## Step 2: Implement data parsing

### `parseData(ModelOwnerInterface $owner, string $data): void`

Parse raw model data into an internal representation. This method is called
before any other data-reading method.

```php
public function parseData(ModelOwnerInterface $owner, string $data): void {
  $this->parsedData = json_decode($data, TRUE) ?? [];
}
```

### Metadata accessors

After `parseData()`, the API calls these methods to extract metadata:

```php
public function getId(): string {
  return $this->parsedData['id'] ?? '';
}

public function getLabel(): string {
  return $this->parsedData['label'] ?? '';
}

public function getStatus(): bool {
  return $this->parsedData['status'] ?? TRUE;
}

public function getVersion(): string {
  return $this->parsedData['version'] ?? '';
}

public function getTags(): array {
  return $this->parsedData['tags'] ?? [];
}

public function getChangelog(): string {
  return $this->parsedData['changelog'] ?? '';
}

public function getTemplate(): bool {
  return $this->parsedData['template'] ?? FALSE;
}

public function getStorage(): string {
  return $this->parsedData['storage'] ?? '';
}

public function getDocumentation(): string {
  return $this->parsedData['documentation'] ?? '';
}
```

### `readComponents(): array`

Convert parsed data into `Component` value objects:

```php
public function readComponents(): array {
  $components = [];

  foreach ($this->parsedData['nodes'] ?? [] as $node) {
    $successors = [];
    foreach ($this->parsedData['edges'] ?? [] as $edge) {
      if ($edge['source'] === $node['id']) {
        $successors[] = new ComponentSuccessor(
          id: $edge['target'],
          conditionId: $edge['conditionId'] ?? '',
        );
      }
    }

    $type = $this->mapNodeType($node['type']);
    $components[] = new Component(
      owner: NULL, // Set by the API during the save cycle.
      id: $node['id'],
      type: $type,
      pluginId: $node['pluginId'],
      label: $node['label'] ?? '',
      configuration: $node['config'] ?? [],
      successors: $successors,
    );
  }

  return $components;
}
```

### `getRawData(): string`

Serialize the current state back to raw data:

```php
public function getRawData(): string {
  return json_encode($this->parsedData, JSON_PRETTY_PRINT);
}
```

## Step 3: Implement the editing UI

### `edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew, bool $readOnly): array`

Return a Drupal render array containing your modeler UI:

```php
public function edit(ModelOwnerInterface $owner, string $id, string $data,
  bool $isNew = FALSE, bool $readOnly = FALSE): array {

  $build = [];

  // Container for the JavaScript modeler canvas.
  $build['canvas'] = [
    '#type' => 'container',
    '#attributes' => ['id' => 'my-modeler-canvas'],
  ];

  // Attach JavaScript libraries.
  $build['#attached']['library'][] = 'my_modeler/editor';

  // Pass data to JavaScript via drupalSettings.
  $build['#attached']['drupalSettings']['myModeler'] = [
    'modelId' => $id,
    'modelData' => $data,
    'isNew' => $isNew,
    'readOnly' => $readOnly,
    'ownerId' => $owner->getPluginId(),
    'saveUrl' => '/modeler-api/save/' . $owner->getPluginId(),
    'configFormUrl' => '/modeler-api/config-form/' . $owner->getPluginId(),
    'components' => $this->prepareComponentsForJs($owner),
  ];

  return $build;
}

protected function prepareComponentsForJs(ModelOwnerInterface $owner): array {
  $result = [];
  foreach ($owner->supportedOwnerComponentTypes() as $type => $name) {
    $plugins = [];
    foreach ($owner->availableOwnerComponents($type) as $id => $plugin) {
      $plugins[$id] = [
        'label' => $plugin->getPluginDefinition()['label'] ?? $id,
      ];
    }
    $result[$name] = $plugins;
  }
  return $result;
}
```

### `convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly): array`

Convert an existing model entity (created by a different modeler) to your
format:

```php
public function convert(ModelOwnerInterface $owner,
  ConfigEntityInterface $model, bool $readOnly = FALSE): array {

  // Get components from the Model Owner.
  $components = $owner->getUsedComponents($model);

  // Build your data format from the components.
  $nodes = [];
  $edges = [];
  foreach ($components as $component) {
    if ($component->getType() === Api::COMPONENT_TYPE_ANNOTATION) {
      continue; // Handle annotations separately.
    }

    $nodes[] = [
      'id' => $component->getId(),
      'type' => $this->mapComponentType($component->getType()),
      'pluginId' => $component->getPluginId(),
      'label' => $component->getLabel(),
      'config' => $component->getConfiguration(),
    ];

    foreach ($component->getSuccessors() as $successor) {
      $edges[] = [
        'source' => $component->getId(),
        'target' => $successor->getId(),
        'conditionId' => $successor->getConditionId(),
      ];
    }
  }

  $data = json_encode([
    'id' => $model->id(),
    'label' => $owner->getLabel($model),
    'nodes' => $nodes,
    'edges' => $edges,
  ]);

  // Generate a new ID for the conversion.
  $id = $model->id();
  return $this->edit($owner, $id, $data, isNew: FALSE, readOnly: $readOnly);
}
```

## Step 4: Implement config forms

### `configForm(ModelOwnerInterface $owner): JsonResponse`

Handle AJAX requests for component configuration forms:

```php
public function configForm(ModelOwnerInterface $owner): JsonResponse {
  $type = (int) $this->request->query->get('type', 0);
  $pluginId = $this->request->query->get('pluginId', '');
  $config = json_decode(
    $this->request->getContent(), TRUE
  )['config'] ?? [];

  // Instantiate the plugin with existing config.
  $plugin = $owner->ownerComponent($type, $pluginId, $config);
  if ($plugin === NULL) {
    return new JsonResponse(['error' => 'Plugin not found'], 404);
  }

  // Build the config form.
  $form = $owner->buildConfigurationForm($plugin);

  // Convert the Drupal form to a JSON-serializable structure.
  // This conversion is modeler-specific.
  $jsonForm = $this->convertFormToJson($form);

  return new JsonResponse($jsonForm);
}
```

!!! tip "Config form delivery patterns"
    The `bpmn_io` modeler returns an `AjaxResponse` that opens an off-canvas
    dialog. The Workflow Modeler returns a `JsonResponse` with form fields
    converted to a JSON structure that React components can render. Choose the
    approach that fits your frontend technology.

## Step 5: Model lifecycle methods

### `prepareEmptyModelData(string &$id): string`

Create raw data for a new empty model:

```php
public function prepareEmptyModelData(string &$id): string {
  if (empty($id)) {
    $id = $this->generateId();
  }
  return json_encode([
    'id' => $id,
    'label' => '',
    'status' => TRUE,
    'nodes' => [],
    'edges' => [],
  ]);
}
```

### `enable(ModelOwnerInterface $owner): ModelerInterface`

Modify raw data to reflect an enabled state:

```php
public function enable(ModelOwnerInterface $owner): ModelerInterface {
  $this->parsedData['status'] = TRUE;
  return $this;
}
```

### `disable(ModelOwnerInterface $owner): ModelerInterface`

```php
public function disable(ModelOwnerInterface $owner): ModelerInterface {
  $this->parsedData['status'] = FALSE;
  return $this;
}
```

### `clone(ModelOwnerInterface $owner, string $id, string $label): ModelerInterface`

Modify raw data for a cloned version:

```php
public function clone(ModelOwnerInterface $owner, string $id,
  string $label): ModelerInterface {
  $this->parsedData['id'] = $id;
  $this->parsedData['label'] = $label;
  return $this;
}
```

## Step 6: Dependency injection

Since `ModelerBase` declares the constructor as `final`, use lazy getter
injection:

```php
protected ?MyParserService $parser = NULL;

protected function getParser(): MyParserService {
  if (!isset($this->parser)) {
    $this->parser = $this->getContainer()->get('my_modeler.parser');
  }
  return $this->parser;
}
```

The base class provides `$this->getContainer()` which returns the DI container.

## JavaScript integration

Your modeler's JavaScript needs to interact with the Modeler API's endpoints:

### Save endpoint

```
POST /modeler-api/save/{owner_id}
Content-Type: application/xml  (or application/json)
Body: <raw model data>
```

### Config form endpoint

```
GET /modeler-api/config-form/{owner_id}?type={type}&pluginId={pluginId}
```

or

```
POST /modeler-api/config-form/{owner_id}
Content-Type: application/json
Body: {"type": 3, "pluginId": "my_action", "config": {...}}
```

### Replay data endpoint (if supported)

```
GET /modeler-api/replay/{owner_id}/{model_id}/{component_id}
```

## Existing implementations for reference

- **BPMN.iO** (`bpmn_io` module): XML/BPMN format with BPMN.js canvas,
  `AjaxResponse` config forms, SVG export.
- **Workflow Modeler** (`modeler` module): JSON format with React Flow,
  `JsonResponse` config forms, lightweight and htmx-compatible.
