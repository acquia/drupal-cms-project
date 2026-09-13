# Modeler Plugin Manager

The Modeler plugin manager discovers and manages plugins that provide the
visual editing experience. A Modeler knows how to render a canvas, parse raw
model data (XML, JSON, etc.), and serialize it back.

## Plugin manager details

| Property | Value |
|----------|-------|
| **Service ID** | `plugin.manager.modeler_api.modeler` |
| **Class** | `Drupal\modeler_api\Plugin\ModelerPluginManager` |
| **Discovery** | PHP attribute (`#[Modeler]`) |
| **Plugin namespace** | `Plugin\ModelerApiModeler` |
| **Interface** | `Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface` |
| **Base class** | `Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase` |
| **Attribute** | `Drupal\modeler_api\Attribute\Modeler` |
| **Alter hook** | `hook_modeler_api_modeler_info_alter()` |
| **Cache tag** | `modeler_api_modeler_plugins` |
| **Fallback** | `fallback` (implements `FallbackPluginManagerInterface`) |

## Attribute definition

```php
#[Modeler(
  id: "my_modeler",
  label: new TranslatableMarkup("My Modeler"),
  description: new TranslatableMarkup("A visual modeler using technology X."),
)]
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | `string` | Yes | Unique plugin ID |
| `label` | `TranslatableMarkup` | No | Human-readable name |
| `description` | `TranslatableMarkup` | No | Brief description |

## Fallback plugin

The `ModelerPluginManager` implements `FallbackPluginManagerInterface`. If a
model references a Modeler plugin that is no longer installed, the manager
returns the built-in `Fallback` plugin instead of throwing an exception. The
fallback simply stores and returns raw data without modification.

```php
// The fallback is automatically used when the real plugin is missing.
$modeler = $modelerManager->createInstance('nonexistent_modeler');
// $modeler is an instance of Fallback.
```

## ModelerInterface

The interface defines the complete contract for Modeler plugins.

### Identity

| Method | Return | Description |
|--------|--------|-------------|
| `label()` | `string` | Plugin label |
| `description()` | `string` | Plugin description |
| `getRawFileExtension()` | `?string` | File extension for raw data (e.g. `xml`, `json`) |
| `generateId()` | `string` | Generate a unique model ID |

### Editing

| Method | Return | Description |
|--------|--------|-------------|
| `isEditable()` | `bool` | Whether this modeler supports in-browser editing |
| `edit($owner, $id, $data, $isNew, $readOnly)` | `array` | Render array for the editing UI |
| `convert($owner, $model, $readOnly)` | `array` | Render array for converting an existing model to this modeler's format |
| `configForm($owner)` | `JsonResponse` | AJAX endpoint returning a component's configuration form |

### Data parsing

| Method | Return | Description |
|--------|--------|-------------|
| `parseData($owner, $data)` | `void` | Parse raw data and prepare internal state |
| `readComponents()` | `Component[]` | Extract components from parsed data |
| `updateComponents($owner)` | `bool` | Check if raw data needs updating for changed owner components |
| `getRawData()` | `string` | Serialize current state back to raw data |

### Metadata extraction

After `parseData()` is called, these methods return metadata extracted from the
raw data:

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Model ID from raw data |
| `getLabel()` | `string` | Model label |
| `getTags()` | `array` | Tags list |
| `getChangelog()` | `string` | Changelog text |
| `getTemplate()` | `bool` | Template flag |
| `getStorage()` | `string` | Storage override |
| `getDocumentation()` | `string` | Documentation text |
| `getStatus()` | `bool` | Enabled/disabled status |
| `getVersion()` | `string` | Version string |

### Model lifecycle

| Method | Return | Description |
|--------|--------|-------------|
| `prepareEmptyModelData(&$id)` | `string` | Raw data for a new empty model |
| `enable($owner)` | `$this` | Modify raw data for enabled state |
| `disable($owner)` | `$this` | Modify raw data for disabled state |
| `clone($owner, $id, $label)` | `$this` | Modify raw data for a cloned model |

## ModelerBase

The abstract base class provides:

- **Final constructor** injecting: `Request`, `UuidInterface`,
  `ExtensionPathResolver`, `FormBuilderInterface`, `LoggerChannelInterface`
- **Final `create()`** -- prevents subclasses from adding DI parameters
- **`getContainer()`** -- returns the service container for lazy getter
  injection
- **`defaultModelConfigForm()`** -- builds the standard model metadata form
  (label, version, status, template, tags, changelog, storage, documentation)
- **Default implementations** for most methods that return neutral values

### Dependency injection in Modeler plugins

Since the constructor is final, use lazy getter injection:

```php
class MyModeler extends ModelerBase {

  protected ?MyService $myService = NULL;

  protected function getMyService(): MyService {
    if (!isset($this->myService)) {
      $this->myService = $this->getContainer()->get('my_module.service');
    }
    return $this->myService;
  }

}
```

## The `edit()` method

The `edit()` method is the core of any Modeler plugin. It must return a Drupal
render array that contains:

1. **The visual canvas** -- HTML container for the modeler UI
2. **Attached libraries** -- JavaScript/CSS for the modeler
3. **drupalSettings** -- Configuration data passed to JavaScript, typically
   including:
    - Model ID and raw data
    - Available components (from the Model Owner)
    - API endpoint URLs for save, config forms, etc.
    - Context and dependency information

### Example: BPMN.iO

The `bpmn_io` modeler renders a BPMN canvas with toolbar widgets:

```php
public function edit(ModelOwnerInterface $owner, string $id, string $data,
  bool $isNew = FALSE, bool $readOnly = FALSE): array {
  $build = [];
  // Canvas container.
  $build['canvas'] = ['#markup' => '<div class="bpmn-canvas"></div>'];
  // Attach BPMN.iO library.
  $build['#attached']['library'][] = 'bpmn_io/modeler';
  // Pass data to JavaScript.
  $build['#attached']['drupalSettings']['bpmnIo'] = [
    'modelId' => $id,
    'data' => $data,
    'isNew' => $isNew,
    'readOnly' => $readOnly,
    'components' => $this->prepareComponentsForJs($owner),
    'saveUrl' => '/modeler-api/save/' . $owner->getPluginId(),
  ];
  return $build;
}
```

### Example: Workflow Modeler

The `modeler` module's `WorkflowModeler` uses React Flow with JSON:

```php
public function edit(ModelOwnerInterface $owner, string $id, string $data,
  bool $isNew = FALSE, bool $readOnly = FALSE): array {
  $build = [];
  $build['modeler'] = ['#markup' => '<div id="modeler-root"></div>'];
  $build['#attached']['library'][] = 'modeler/react-ui';
  $build['#attached']['drupalSettings']['modeler'] = [
    'modelData' => $data,
    'components' => $owner->availableOwnerComponents(...),
  ];
  return $build;
}
```

## The `configForm()` method

This method handles AJAX requests from the modeler UI when a user clicks on a
component to configure it. It receives the component type and ID from the
request, builds a Drupal form via the Model Owner's
`buildConfigurationForm()`, and returns it as a JSON response.

The BPMN.iO modeler returns an `AjaxResponse` with an off-canvas dialog. The
Workflow Modeler returns a `JsonResponse` with form fields converted to a
JSON-serializable structure.

## Existing implementations

### BPMN.iO (`bpmn_io`)

- **Plugin ID:** `bpmn_io`
- **Format:** XML (BPMN 2.0)
- **File extension:** `xml`
- **UI technology:** BPMN.js canvas with custom toolbar
- **Config form delivery:** `AjaxResponse` with off-canvas dialog
- **Features:** SVG export, minimap, search, copy/paste, layout

### Workflow Modeler (`workflow_modeler`)

- **Plugin ID:** `workflow_modeler`
- **Format:** JSON
- **File extension:** `json`
- **UI technology:** React Flow
- **Config form delivery:** `JsonResponse` with form-to-JSON conversion
- **Features:** Context switching, htmx support, lightweight JSON structure
