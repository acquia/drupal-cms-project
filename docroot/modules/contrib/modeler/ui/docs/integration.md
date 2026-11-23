# Integration Patterns

Reference for integrating the workflow modeler with external systems through the Modeler API.

## Integration Overview

### Modeler API Architecture
The modeler integrates with any Drupal workflow system that implements the `ModelerInterface`:

```php
// Required interface for workflow system integration
interface ModelerInterface {
  public function ownerComponents(): array;
  public function parseData($data): ModelData;
  public function getRawData(): string;
  public function prepareEmptyModelData(): ModelData;
  public function convert($sourceModel, $targetFormat): ModelData;
  public function configForm(ModelOwnerInterface $owner): JsonResponse;
  public function edit(): array;
}
```

### Integration Points
1. **Component Discovery**: Available workflow components from the system
2. **Data Exchange**: JSON model format with annotations
3. **Configuration**: Dynamic forms for component configuration
4. **Execution**: Replay data for workflow execution visualization

## Component System Integration

### Component Provider Implementation
```php
// Example: model owner module integration
class ExampleModeler implements ModelerInterface {
  
  public function ownerComponents(): array {
    // Components are returned as a flat list. Each component carries an
    // integer componentType that identifies its kind. The frontend resolves
    // the string type name (start, element, link, ...) via the typeMap.
    return [
      [
        'plugin' => 'entity:entity_create',
        'label' => 'Create Entity',
        'description' => 'Triggers when an entity is created',
        'documentationUrl' => 'https://docs.example.com/create-entity',
        'componentType' => 1, // start/event
        'provider' => 'eca_content',
      ],
      [
        'plugin' => 'entity:entity_save',
        'label' => 'Save Entity',
        'description' => 'Saves an entity to the database',
        'documentationUrl' => 'https://docs.example.com/save-entity',
        'componentType' => 4, // element/action
        'provider' => 'eca_content',
      ],
      [
        'plugin' => 'entity:entity_is_new',
        'label' => 'Entity is New',
        'description' => 'Checks if entity is newly created',
        'documentationUrl' => 'https://docs.example.com/entity-is-new',
        'componentType' => 5, // link/condition
        'provider' => 'eca_content',
      ],
    ];
  }

  public function configForm(ModelOwnerInterface $owner): JsonResponse {
    // Parse JSON request body with component_type, plugin_id, etc.
    $json_data = json_decode($this->request->getContent(), TRUE);
    $plugin = $owner->ownerComponent($json_data['component_type'], $json_data['plugin_id']);

    // Return JSON with form array or error string
    if (!$plugin) {
      return new JsonResponse(['error' => 'Invalid plugin.']);
    }
    $form = $owner->buildConfigurationForm($plugin, $json_data['model_id'], $json_data['is_new']);
    $form = $this->formBuilder->getForm(Wrapper::class, $form);

    // The FormToJsonConverter service handles conversion and YAML schema
    // discovery automatically for textarea fields.
    $schema_key = $owner->getPluginSchemaKey($plugin);
    return new JsonResponse([
      'form' => $this->formToJsonConverter->convert($form, $schema_key),
    ]);
  }
}
```

### Component Properties Structure
```php
// Each component in ownerComponents() includes:
$component = [
  'plugin' => 'unique_plugin_id',           // Required: Unique identifier
  'label' => 'Human Readable Label',         // Required: Display name
  'description' => 'Component description',  // Optional: Tooltip text
  'documentationUrl' => 'https://docs...',   // Optional: External documentation
  'provider' => 'module_name',               // Optional: Provider module
  'componentType' => 4,                      // Integer type constant (see below)
];
```

### Component Type Constants

The backend provides a `typeMap` (via `Api::COMPONENT_TYPE_NAMES`) that maps
integer constants to string type names. The canonical mapping is:

| Integer | String name | Typical label |
|---------|-------------|---------------|
| 1 | `start` | Event |
| 2 | `subprocess` | Subprocess |
| 3 | `swimlane` | Swimlane |
| 4 | `element` | Action |
| 5 | `link` | Condition |
| 6 | `gateway` | Gateway |
| 7 | `annotation` | Annotation |

The frontend resolves the string `type` for each component at load time from
its integer `componentType` using the `typeMap` provided in
`drupalSettings.modeler.typeMap`. Saved model data uses the integer
`componentType`, not the string `type`.

### Component Labels

Display labels for component types (e.g., "Event" vs. "Trigger") are provided
by the model owner via `drupalSettings.modeler_api.component_labels` and
`component_labels_plural`. The frontend falls back to generic defaults when
labels are not provided:

```json
{
  "component_labels": {
    "start": "Event",
    "element": "Action",
    "link": "Condition",
    "gateway": "Gateway",
    "subprocess": "Subprocess"
  },
  "component_labels_plural": {
    "start": "Events",
    "element": "Actions",
    "link": "Conditions",
    "gateway": "Gateways",
    "subprocess": "Subprocesses"
  }
}
```

## Data Exchange Format

### Model JSON Structure

Nodes carry an integer `componentType` as their canonical type identifier.
The string `type` field is resolved by the frontend at load time via the
`typeMap` and is not persisted. For backward compatibility the backend also
accepts the legacy string `type` when `componentType` is absent.

```json
{
  "id": "workflow_id",
  "version": "1.0.0",
  "metadata": {
    "label": "Model Name",
    "description": "Workflow description",
    "executable": true,
    "tags": ["tag1", "tag2"],
    "changelog": ""
  },
  "nodes": [
    {
      "id": "event_1",
      "componentType": 1,
      "plugin": "entity:entity_create",
      "label": "Create Entity",
      "position": {"x": 100, "y": 100},
      "configuration": {
        "entity_type": "node"
      },
      "annotation": "Trigger for content creation"
    },
    {
      "id": "action_1",
      "componentType": 4,
      "plugin": "entity:entity_save",
      "label": "Save Entity",
      "position": {"x": 300, "y": 100},
      "configuration": {
        "immediate_save": true
      },
      "annotation": "Save action for persistence"
    }
  ],
  "edges": [
    {
      "id": "edge_1",
      "source": "event_1",
      "target": "action_1",
      "condition": "entity:entity_is_new",
      "conditionLabel": "Entity is New",
      "conditionConfiguration": {},
      "annotation": "Condition check before save",
      "controlOffset": {"x": 0, "y": 0}
    }
  ]
}
```

### Data Parsing Implementation
```php
public function parseData($data): ModelData {
  $modelData = json_decode($data);
  
  // Create annotation components
  $annotationComponents = [];
  
  foreach ($modelData->nodes as $node) {
    if (!empty($node->annotation)) {
      $annotationComponents[] = new Component(
        $this->owner,
        $node->id . '_annotation',
        Api::COMPONENT_TYPE_ANNOTATION,
        '',
        $node->annotation,
        [],
        [new ComponentSuccessor($node->id, '')]
      );
    }
  }
  
  return new ModelData(
    $modelData->nodes,
    $modelData->edges,
    array_merge($modelData->nodes, $annotationComponents)
  );
}
```

## Configuration Form System

### Request Payload
The frontend sends a JSON POST body with the following fields:

```json
{
  "component_type": "4",
  "component_id": "node-1",
  "model_id": "my_workflow_model",
  "is_new": false,
  "plugin_id": "entity:entity_create",
  "configuration": { "entity_type": "node" }
}
```

- `component_type` — Numeric type (`"4"` for elements, `"5"` for link conditions, etc.)
- `component_id` — Node/edge ID from the canvas
- `model_id` — ID of the model being edited
- `is_new` — Whether the model is newly created (not yet saved)
- `plugin_id` — Plugin identifier for the component
- `configuration` — Current configuration values (empty `{}` for new components)

### Response Format
The backend returns a JSON object with either a `form` array or an `error` string:

```json
// Success — form is an array of field objects
{
  "form": [
    { "key": "entity_type", "type": "select", "title": "Entity Type", "options": { "node": "Node", "user": "User" }, "required": true, "default_value": "", "token_support": true },
    { "key": "immediate_save", "type": "checkbox", "title": "Save immediately", "default_value": true }
  ]
}

// Error
{ "error": "Plugin not editable." }

// No configuration form available
{}
```

### Dynamic Form Generation

Form conversion is handled by the `FormToJsonConverter` service, which also
performs automatic YAML schema discovery for textarea fields (see
[Structured YAML Editing](#structured-yaml-editing-yaml-schemas) below).

```php
public function configForm(ModelOwnerInterface $owner): JsonResponse {
  $data = [];
  $json_data = json_decode($this->request->getContent(), TRUE);

  if (!$json_data) {
    $data['error'] = $this->t('Invalid data.');
    return new JsonResponse($data);
  }

  $plugin = $owner->ownerComponent($json_data['component_type'], $json_data['plugin_id']);
  if (!$plugin) {
    $data['error'] = $this->t('Invalid plugin.');
    return new JsonResponse($data);
  }

  if (!$owner->ownerComponentEditable($plugin)) {
    $data['error'] = $this->t('Plugin not editable.');
    return new JsonResponse($data);
  }

  // Build form and convert to JSON-serializable array.
  // The FormToJsonConverter service auto-discovers YAML schemas.
  $form = $owner->buildConfigurationForm($plugin, $json_data['model_id'], $json_data['is_new']);
  $form = $this->formBuilder->getForm(Wrapper::class, $form);
  $schema_key = $owner->getPluginSchemaKey($plugin);
  $data['form'] = $this->getFormToJsonConverter()->convert($form, $schema_key);
  return new JsonResponse($data);
}
```

### Token Support in Form Fields

Form fields can accept token drops from the replay panel. The backend controls which fields support tokens via two mechanisms:

1. **Per-field `token_support`**: Set `"token_support": true` on individual form field objects to indicate they accept token replacement values (e.g., `[node:title]`). Fields without this flag will visually reject token drops.

2. **Global `replace_tokens` checkbox**: If the form includes a checkbox field with `"key": "replace_tokens"`, checking it enables token drops on **all** fields in the form, regardless of their individual `token_support` setting.

```json
{
  "form": [
    { "key": "replace_tokens", "type": "checkbox", "title": "Replace tokens" },
    { "key": "value", "type": "textfield", "title": "Value", "token_support": true },
    { "key": "message", "type": "textarea", "title": "Message", "token_support": true },
    { "key": "limit", "type": "number", "title": "Limit" }
  ]
}
```

In this example, "Value" and "Message" always accept token drops. "Limit" only accepts them when the "Replace tokens" checkbox is checked. The modeler's Label and Annotation fields (managed by the modeler itself, not the plugin form) never accept token drops.

During a token drag operation, eligible fields highlight with a border glow while non-eligible fields dim to indicate they cannot receive tokens.

### Structured YAML Editing (YAML Schemas)

Textarea fields can be enhanced with a structured YAML editor. When the
backend discovers a YAML schema for a textarea field, it sends the schema
inline on the field object as `yaml_schema`. The frontend renders a
form-based editor instead of a plain textarea.

#### Schema discovery convention

For a plugin with config schema key `{SCHEMA_KEY}` and a textarea field
`{FIELD_KEY}`, a YAML schema is discovered at the Drupal config schema key:

```
yaml.{SCHEMA_KEY}.{FIELD_KEY}
```

For example, an ECA event plugin `eca_base:eca_tool` with a textarea field
`arguments` would look up `yaml.eca.event.plugin.eca_base:eca_tool.arguments`.

#### Backend services

Two Drupal services handle this (defined in `modeler.services.yml`):

- **`modeler.yaml_schema_lookup`** (`YamlSchemaLookup`): Queries the
  `TypedConfigManager` for schema definitions, filters out `Undefined`
  fallbacks, and converts Drupal config schema to the YamlEditor JSON format.
- **`modeler.form_to_json_converter`** (`FormToJsonConverter`): Converts a
  Drupal form render array to JSON. For textarea fields, it calls
  `YamlSchemaLookup::lookup()` to auto-discover schemas.

#### Drupal config schema → YamlEditor type mapping

| Drupal schema type | YamlEditor type |
|-------------------|-----------------|
| `string`, `text`, `label`, `email`, `uri`, `path`, etc. | `string` |
| `string` with `Choice` constraint | `string` with `options` |
| `integer`, `weight` | `number` (step: 1) |
| `float` | `number` |
| `boolean` | `boolean` |
| `sequence` | `list` |
| `mapping` | `mapping` |

Constraints are also converted: `NotBlank` → `required`, `Choice` → `options`
(static or callback-based), `Range` → `min`/`max`.

#### Response format with YAML schema

```json
{
  "form": [
    {
      "key": "arguments",
      "type": "textarea",
      "title": "Arguments",
      "yaml_schema": {
        "type": "mapping",
        "properties": {
          "method": { "type": "string", "label": "HTTP Method", "options": {"GET": "GET", "POST": "POST"} },
          "timeout": { "type": "number", "label": "Timeout", "min": 1, "max": 300, "step": 1 },
          "headers": {
            "type": "list",
            "label": "Headers",
            "items": {
              "type": "mapping",
              "properties": {
                "name": { "type": "string", "label": "Name", "required": true },
                "value": { "type": "string", "label": "Value" }
              }
            }
          }
        }
      }
    }
  ]
}
```

#### Type detection

Drupal's `TypedConfigManager::getDefinition()` replaces the `type` field with
the fully resolved schema key name (not the base type like `mapping`). The
`YamlSchemaLookup` service uses `resolveBaseType()` to determine the effective
type by inspecting the definition's PHP `class` property (e.g.,
`Drupal\Core\Config\Schema\Mapping`) and structural keys (`mapping`,
`sequence`), falling back to the plain `type` string for unresolved
sub-definitions.

### Form Validation
```php
public function validateConfig($component_id, $values): array {
  $errors = [];
  
  switch ($component_id) {
    case 'entity:entity_create':
      if (empty($values['entity_type'])) {
        $errors['entity_type'] = 'Entity type is required';
      }
      if (empty($values['bundle'])) {
        $errors['bundle'] = 'Bundle is required';
      }
      break;
      
    case 'entity:entity_save':
      if (!isset($values['save_mode'])) {
        $errors['save_mode'] = 'Save mode is required';
      }
      break;
  }
  
  return $errors;
}
```

## Execution Replay Integration

### Replay Data Provider
```php
public function getReplayData($modelId, $componentId): array {
  // Query execution logs from your system
  $query = \Drupal::database()->select('workflow_execution_log');
  $query->condition('model_id', $modelId);
  $query->condition('event_id', $componentId);
  $query->orderBy('timestamp', 'DESC');
  $query->range(0, 100); // Limit to recent executions
  
  $results = $query->execute()->fetchAll();
  
  // Transform to replay entry format
  $replayEntries = [];
  foreach ($results as $result) {
    $replayEntries[] = [
      'model_id' => $result->model_id,
      'event_id' => $result->event_id,
      'history' => $this->parseExecutionHistory($result->data),
      'timestamp' => $result->timestamp,
      'user' => $result->uid,
      'ip' => $result->ip,
      'url' => $result->url
    ];
  }
  
  return $replayEntries;
}

private function parseExecutionHistory($data): array {
  // Parse execution data into step format
  $steps = [];
  
  // Add execution start step
  $steps[] = [
    'type' => 'started',
    'id' => $data['entity_id'],
    'data' => ['entity' => $data['entity']]
  ];
  
  // Add condition evaluation steps
  foreach ($data['conditions'] as $condition) {
    $steps[] = [
      'type' => $condition['success'] ? 'add successor' : 'ignore successor',
      'id' => $condition['node_id'],
      'successorId' => $condition['successor_id'],
      'conditionId' => $condition['condition_plugin']
    ];
  }
  
  return $steps;
}
```

### Replay Endpoint Implementation
```php
// In your module's routing.yml
modeler_api.replay:
  path: '/modeler-api/replay'
  defaults:
    _controller: '\Drupal\your_module\Controller\ReplayController'
    _format: json
  methods: [POST]
  requirements:
    _csrf_token: 'csrf_token'

// Controller implementation
class ReplayController extends ControllerBase {
  
  public function content(Request $request): JsonResponse {
    $modelId = $request->get('model_id');
    $componentId = $request->get('component_id');
    
    try {
      $replayData = $this->replayService->getReplayData($modelId, $componentId);
      return new JsonResponse($replayData);
    } catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }
}
```

## Settings Integration

### Drupal Settings Structure
```php
// In your module's .routing.yml or hook_menu
your_module.modeler:
  path: '/admin/structure/workflow/modeler'
  defaults:
    _form: '\Drupal\your_module\Form\ModelerForm'
    _title: 'Workflow Modeler'
  requirements:
    _permission: 'administer workflows'

// Form implementation
class ModelerForm extends ConfigFormBase {
  
  public function getFormId(): string {
    return 'your_module_modeler_settings';
  }
  
  public function build(array $form, FormStateInterface $form_state): array {
    return [
      'default_components' => [
        '#type' => 'checkboxes',
        '#title' => 'Default Components',
        '#options' => $this->getAvailableComponents(),
        '#default_value' => \Drupal::config('your_module.settings')->get('default_components')
      ],
      'favorite_components' => [
        '#type' => 'textfield',
        '#title' => 'Favorite Components (comma-separated)',
        '#default_value' => \Drupal::config('your_module.settings')->get('favorite_components'),
        '#description' => 'Enter plugin IDs to show as favorites'
      ]
    ];
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = \Drupal::configFactory()->getEditable('your_module.settings');
    $config->set('default_components', $form_state->getValue('default_components'));
    $config->set('favorite_components', $form_state->getValue('favorite_components'));
  }
}
```

### Frontend Configuration
```javascript
// drupalSettings passed to frontend
drupalSettings.your_module = {
  modeler_api: {
    token_url: '/session/token',
    save_url: '/modeler-api/save', 
    config_url: '/modeler-api/config-form',
    replay_url: '/modeler-api/replay',
    test_url: '/modeler-api/test',         // Test endpoint (optional)
    collection_url: '/admin/structure/workflow',
    metadata: {
      version: '1.0.0',
      label: 'My Workflow Model',
      description: 'Custom workflow modeling interface',
      storage: 'config',
      executable: true,
      template: false,
      tags: [],
      changelog: ''
    }
  },
  modeler: {
    components: <?= json_encode($componentDefinitions) ?>,
    favorite_components: <?= json_encode($favoriteComponents) ?>
  }
};
```

## Error Handling and Validation

### API Response Validation
```php
public function validateModelData($data): array {
  $errors = [];
  $decoded = json_decode($data);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    $errors['json'] = 'Invalid JSON format';
    return $errors;
  }
  
  // Validate required fields
  if (!isset($decoded->nodes) || !is_array($decoded->nodes)) {
    $errors['nodes'] = 'Nodes array is required';
  }
  
  if (!isset($decoded->edges) || !is_array($decoded->edges)) {
    $errors['edges'] = 'Edges array is required';
  }
  
  // Validate node structure
  foreach ($decoded->nodes as $index => $node) {
    if (!isset($node->id)) {
      $errors['nodes'][$index] = 'Node ID is required';
    }
    
    if (!isset($node->plugin)) {
      $errors['nodes'][$index] = 'Plugin ID is required';
    }
    
    // Validate plugin exists
    if (!$this->isValidPlugin($node->plugin)) {
      $errors['nodes'][$index] = 'Invalid plugin: ' . $node->plugin;
    }
  }
  
  return $errors;
}
```

### Security Considerations
```php
// CSRF protection
public function saveModel(Request $request): JsonResponse {
  // Validate CSRF token
  $token = $request->headers->get('X-CSRF-Token');
  if (!$this->csrfToken->validate($token)) {
    return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
  }
  
  // Validate user permissions
  if (!$this->currentUser->hasPermission('administer workflows')) {
    return new JsonResponse(['error' => 'Access denied'], 403);
  }
  
  // Validate model data
  $modelData = $request->get('data');
  $errors = $this->validateModelData($modelData);
  
  if (!empty($errors)) {
    return new JsonResponse(['errors' => $errors], 400);
  }
  
  // Process valid data
  $this->saveModelData($modelData);
  
  return new JsonResponse(['success' => true]);
}
```

## Integration Testing

### Unit Tests
```php
class ModelerIntegrationTest extends UnitTestCase {
  
  public function testComponentDiscovery(): void {
    $modeler = new ExampleModeler();
    $components = $modeler->ownerComponents();
    
    $this->assertArrayHasKey('triggers', $components);
    $this->assertArrayHasKey('actions', $components);
    $this->assertArrayHasKey('conditions', $components);
    
    // Test component structure
    foreach ($components['actions'] as $component) {
      $this->assertArrayHasKey('plugin', $component);
      $this->assertArrayHasKey('label', $component);
      $this->assertNotEmpty($component['plugin']);
      $this->assertNotEmpty($component['label']);
    }
  }
  
  public function testDataParsing(): void {
    $modeler = new ExampleModeler();
    
    $jsonData = '{"nodes": [{"id": "test", "plugin": "test_plugin"}], "edges": []}';
    $modelData = $modeler->parseData($jsonData);
    
    $this->assertCount(1, $modelData->getNodes());
    $this->assertEmpty($modelData->getEdges());
    $this->assertEquals('test', $modelData->getNodes()[0]->getId());
  }
}
```

### Integration Tests
```php
class ModelerIntegrationTest extends BrowserTestBase {
  
  public function testModelerInterface(): void {
    // Test modeler loads in browser
    $this->drupalGet('/admin/structure/workflow/modeler');
    
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.workflow-modeler');
    $this->assertSession()->elementExists('css', '[data-testid="flow-canvas"]');
  }
  
  public function testComponentAvailability(): void {
    $this->drupalGet('/admin/structure/workflow/modeler');
    
    // Wait for modeler to load
    $this->assertSession()->waitForElement('css', '[data-testid="flow-canvas"]');
    $this->assertSession()->elementExists('css', '.workflow-modeler');
  }
}
```

## Integration Guidelines

### Implementation Checklist
- [ ] Implement `ModelerInterface` with all required methods
- [ ] Provide `ownerComponents()` with proper component structure
- [ ] Create dynamic configuration forms using Form API
- [ ] Implement proper CSRF protection
- [ ] Add permission checks for all operations
- [ ] Validate all input data and API responses
- [ ] Provide comprehensive error handling
- [ ] Include documentation URLs for components
- [ ] Support annotation components for documentation
- [ ] Implement replay data provider for execution visualization

### Performance Considerations
- [ ] Cache component definitions for faster loading
- [ ] Use lazy loading for large component libraries
- [ ] Optimize configuration form rendering
- [ ] Implement proper database queries with indexes
- [ ] Consider batch operations for model data

### Security Requirements
- [ ] CSRF token validation on all state-changing operations
- [ ] Permission checks for all functionality
- [ ] Input validation and sanitization
- [ ] Rate limiting for API endpoints
- [ ] Audit logging for all model operations
- [ ] Proper error handling without information leakage

## Component Properties

Each component in the `ownerComponents()` return value can have:

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `plugin` | string | Yes | Unique plugin identifier |
| `label` | string | Yes | Display name in quick-add popups |
| `description` | string | No | Tooltip text shown on hover |
| `documentationUrl` | string\|null | No | URL to external documentation page |
| `provider` | string | No | Provider/module name for filtering |
| `componentType` | number | Yes | Integer type constant (1=start, 4=element, 5=link, 6=gateway, 2=subprocess) |

### Documentation URL Requirements

When providing `documentationUrl`:
- Must be an absolute URL (https://...)
- Remote server must have CORS headers configured to allow requests
- Should include `Access-Control-Allow-Headers: Accept, Content-Type, X-Requested-With`
- Content is extracted from `[data-md-component="content"]` selector (MkDocs format)

## Reference Implementation

The `bpmn_io` module serves as a reference implementation for Modeler API integration patterns.

## Key Integration Points

- **Model Loading**: API provides JSON data → Plugin parses into internal format → UI renders
- **Model Editing**: User interactions → Update internal model → Save to hidden field
- **Model Saving**: Save button → Export to API with CSRF token → API handles persistence
- **Configuration Forms**: Launched via `/modeler-api/config-form` route as off-canvas dialogs
- **Component Templates**: Retrieved from owner via `ownerComponents()` and `supportedOwnerComponentTypes()`

## Data Flow

1. Workflow module implements `ModelerInterface`
2. Modeler API coordinates between workflow module and UI
3. Modeler plugin renders React UI
4. User interactions update Zustand store
5. Save action serializes state to JSON
6. JSON passed back to workflow module via API
7. Workflow module persists model data

## Configuration

The workflow system that integrates with the modeler can configure:

- **Workflow Models Location**: Where models are stored/managed
- **Create New Model**: How new models are created
- **Component Types**: Available workflow components
- **Configuration Forms**: Forms for each component type
- **Execution Data**: Replay data format and structure
- **Favorite Components**: Per-type favorite plugin lists via `favorite_components` in `drupalSettings.modeler`
- **Contexts**: Workflow contexts that restrict available components, with optional dependency constraints
- **Close Behavior**: `stayInContextOnClose` flag controls close behavior

## Drupal Settings Structure

The modeler receives its configuration via `drupalSettings`:

```javascript
drupalSettings: {
  modeler: {
    modelId: "workflow_id",           // Model identifier
    modelData: "...",                 // JSON string of model data
    isNew: false,                     // Whether creating a new model
    components: [...],                // Available workflow components (with componentType)
    typeMap: {1:"start", 2:"subprocess", 3:"swimlane", 4:"element", 5:"link", 6:"gateway", 7:"annotation"},
    favorite_components: {...},       // Favorites keyed by integer componentType
    selectComponentId: "node_id",     // Auto-select this node on load
    selectContextId: "context_id",    // Auto-select this context on load
    replayData: [...],                // Execution replay data (optional, initial load)
    stayInContextOnClose: false,      // Close behavior
    contexts: [...],                  // Available workflow contexts (see Context System)
    global_tokens: {...}              // Global tokens available site-wide (see Global Tokens)
  },
  modeler_api: {
    token_url: "/session/token",      // CSRF token endpoint
    save_url: "/modeler-api/save",    // Model save endpoint
    config_url: "/modeler-api/config-form", // Configuration form endpoint
    replay_url: "/modeler-api/replay", // Replay execution data endpoint (POST)
    test_url: "/modeler-api/test",     // Test endpoint for live event testing (POST, optional)
    collection_url: "/admin/config/workflow", // Return URL on close
    component_labels: {               // Model-owner display labels (optional)
      start: "Event", element: "Action", link: "Condition",
      gateway: "Gateway", subprocess: "Subprocess"
    },
    component_labels_plural: {        // Plural forms (optional)
      start: "Events", element: "Actions", link: "Conditions",
      gateway: "Gateways", subprocess: "Subprocesses"
    },
    metadata: {
      version: "1.0.0",
      label: "Model Name",
      description: "...",
      storage: "config",
      executable: true,
      template: false,
      tags: [],
      changelog: ""
    }
  }
}
```

## Context Config (Auto-Fill for New Components)

The modeler supports pre-filling configuration values for newly added components:
- **Source**: PHP backend passes `setContextConfig` (JSON key/value map) via `drupalSettings.modeler` from the `?contextConfig=` query parameter
- **Storage**: Stored in Zustand store as `contextConfig: Record<string, string>`
- **Initialization**: Loaded in `useModelDataLoader` during app bootstrap
- **Application**: In `useConfigurationLoader`, when a new component (empty configuration) is selected:
  1. The contextConfig values are merged into the configuration sent to the backend
  2. The backend's `setConfiguration()` receives these values before building the form
  3. Form fields matching contextConfig keys render with the pre-filled values
  4. The values are persisted into the node/edge store data
- **Scope**: Applies to both nodes (actions/events) and edge conditions
- **Detection**: A component is considered "new" when its configuration object is empty (`{}`)

## Context System

Contexts restrict which components are available in the modeler based on workflow purpose. The PHP backend provides contexts via `drupalSettings.modeler_api.contexts`, and the frontend filters component lists accordingly.

### Context Data Structure
```json
{
  "contexts": [
    {
      "id": "ctx_content_editing",
      "topic": "Content Editing",
      "model_owner": "example_owner",
      "components": {
        "start": { "plugins": ["content_entity:insert", "content_entity:update"] },
        "element": { "plugins": ["action:save", "action:publish"] },
        "link": { "plugins": ["condition:is_new", "condition:has_role"] }
      }
    }
  ]
}
```

### Dependency Definitions

Dependencies are delivered separately via `drupalSettings.modeler_api.dependencies` and constrain which plugins can be used based on which predecessor plugins are present in the current workflow. The structure is keyed by component type, then by plugin ID:

```json
{
  "dependencies": {
    "link": {
      "route_match": [
        { "type": "start", "id": "kernel:controller" }
      ]
    },
    "element": {
      "form_add_textfield": [
        { "type": "start", "id": "form:form_build" }
      ]
    }
  }
}
```

Dependency data conforms to the JSON schema at `modeler_api/config/schema/dependency_list.schema.json`.

### Auto-Selection on Load
When `drupalSettings.modeler.selectContextId` is set (from the `?context=` query parameter), the modeler automatically selects that context on initialization if it exists in the available contexts list.

### Component Filtering (`useContextFilter` hook)
- When a context is selected, only plugins listed in that context appear in quick-add popups
- When no context is selected, all components pass the context check
- **Dependency resolution**: If a plugin has entries in the global dependency definitions, it only appears when the current workflow contains at least one dependency component (checked against `node.data.plugin` for node types, `edge.data.condition` for `link` types)
- Both the context filter and the dependency filter must pass for a component to be included

### Favorite Suppression
When a context is active, favorite status is ignored in quick-add dropdowns — components appear in plain alphabetical order with no star indicators or dividers.

### Schema Reference
Context data conforms to the JSON schema at `modeler_api/config/schema/context_list.schema.json`. The `ContextComponentType` values are: `start`, `subprocess`, `swimlane`, `element`, `link`, `gateway`, `annotation`.

## Global Tokens

The backend can provide a set of site-wide tokens via `drupalSettings.modeler_api.global_tokens`. They are consumed by the `[` token picker: `utils/tokenPickerData.ts` turns them into the picker's `global` category, so users insert them straight into the field they are editing. The review panel does not list them.

### Data Structure

```json
{
  "[current-date:custom:?]": {
    "name": "Custom format",
    "description": "A custom date format.",
    "dynamic": true,
    "raw token": "[current-date:custom:?]",
    "token": "custom:?",
    "value": "the value"
  },
  "[current-page:content-language]": {
    "name": "Content language",
    "description": "The active content language.",
    "type": "language",
    "raw token": "[current-page:content-language]",
    "token": "content-language",
    "value": "en",
    "children": {
      "[current-page:content-language:direction]": {
        "name": "Direction",
        "raw token": "[current-page:content-language:direction]",
        "token": "content-language:direction",
        "parent": "[current-page:content-language]",
        "value": "ltr"
      }
    }
  }
}
```

### Token Entry Properties

| Property | Type | Description |
|----------|------|-------------|
| `name` | string | Human-readable label displayed in the tree |
| `description` | string? | Optional description (not displayed, available for future tooltips) |
| `dynamic` | boolean? | Whether the token accepts a dynamic parameter |
| `type` | string? | Token type classification |
| `raw token` | string | Full bracket-wrapped token string (e.g., `[site:name]`), inserted into the field when the token is used |
| `token` | string | Token path without the prefix (e.g., `name`) |
| `value` | any? | Current resolved value |
| `parent` | string? | Raw token of the parent (present on child tokens) |
| `children` | object? | Nested tokens, same structure recursively |

### Frontend Behavior

- The `transformGlobalToken` helper in `ReplayDataRenderer.tsx` converts the Drupal structure into the normalized token-node format (`label`/`token`/`value`/`data`)
- `buildGlobalNodes()` in `utils/tokenPickerData.ts` maps those nodes into the `[` token picker's `global` category (and the template tokens into its `template` category)
- Nested `children` become drill-down levels inside the picker
- Choosing a token with **Use →** inserts its `raw token` value (the bracket-wrapped token string) at the cursor
- Global tokens are not rendered in the review panel - the picker is the only place they appear

## Replay Data Endpoint

The `replay_url` endpoint accepts POST requests with `{ modelId, componentId }` and returns `ReplayEntry[]`:

```typescript
interface ReplayEntry {
  model_id: string;      // Model ID
  event_id: string;      // Event component ID
  history: unknown[];    // Array of replay steps
  timestamp: string;     // ISO-8601 execution timestamp
  user: string | { name: string };  // User who triggered the execution
  ip: string;            // Client IP address
  url: string;           // Request URL
}
```

Each entry's `history` array contains replay steps with `type` (started, execute, add successor, ignore successor, access denied), component IDs, successor IDs, condition IDs, and optional token data. The modeler validates all entries using `validateReplayEntries()` from `utils/validation.ts`, dropping invalid entries with warnings.

## Test Endpoint

The optional `test_url` endpoint enables live workflow testing from the modeler. It uses a two-phase POST protocol: **initiation** and **polling**.

### Phase 1 — Initiation

```
POST test_url
Content-Type: application/json
X-CSRF-Token: <token>

{ "modelId": "workflow_id", "componentId": "event_1" }
```

**Success response:**
```json
{ "jobId": "unique-job-identifier" }
```

The response may also include a `warning` property — the frontend will show it as a Drupal warning message but continue with polling:
```json
{ "jobId": "unique-job-identifier", "warning": "Something to be aware of" }
```

**Error response:**
```json
{ "error": "Error description shown to the user" }
```

### Phase 2 — Polling

The frontend polls the same `test_url` every 1.5 seconds with the `jobId`:

```
POST test_url
Content-Type: application/json
X-CSRF-Token: <token>

{ "jobId": "unique-job-identifier" }
```

**Still waiting:**
```json
{ "status": "waiting" }
```

**Test complete — returns replay data:**
```json
[
  { "id": "event_1", "type": "event", "data": { ... } },
  { "id": "action_1", "type": "action", "successorId": "action_1", "data": { ... } }
]
```

The replay data array uses the same `ReplayStep` format as the `history` array in `ReplayEntry`. When received, the frontend wraps the steps in a single `ReplayEntry` and loads them into the ReplayPanel for visualization. Since this produces only one entry, the entry selector dropdown is not shown (it only appears with 2+ entries from the replay loader).

**Error during polling:**
```json
{ "error": "The test execution failed" }
```

### Availability Rules

- If neither `test_url` nor `replay_url` is configured, the ReplayPanel is hidden entirely and the "Load replay data" button in the PropertyPanel is also hidden.
- For new models (`drupalSettings.modeler.isNew === true`), both `test_url` and `replay_url` are treated as unavailable — new models must be saved before they can be tested or replayed.
- The Test button only appears when an event node is selected (or auto-detected when only one event exists in the model) AND `test_url` is configured.

### User Feedback via Drupal.Message

When loading replay data, the `useReplayLoader` hook provides user feedback through Drupal's native message system:

- **Empty replay data** (valid response but no entries): Shows a **warning** message — "No replay data available for this event."
- **Error in response** (response contains an `error` property): Shows an **error** message with the error text from the backend.

Messages are created using `new Drupal.Message().add(text, { type })`. The modeler's messages container (`useMessagesContainer`) automatically intercepts and displays these in the floating toolbar area with auto-fade behavior. See `docs/ui-components.md` (Messages System section) for full details on how messages are displayed and managed.

## Drupal.Message Integration

The modeler bridges Drupal's native `Drupal.Message` API into its own UI:

```typescript
// Creating messages (used by useReplayLoader and other hooks)
new Drupal.Message().add('Message text.', { type: 'warning' });  // warning
new Drupal.Message().add('Error text.', { type: 'error' });      // error
new Drupal.Message().add('Success text.', { type: 'status' });   // status (default)

// Clearing all messages (used by the toolbar clear button)
new Drupal.Message().clear();
```

TypeScript types for `Drupal.Message` are declared in `src/types/drupal.d.ts` (`DrupalMessageMessenger` interface).
