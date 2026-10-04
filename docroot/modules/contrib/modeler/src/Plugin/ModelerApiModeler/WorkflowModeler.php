<?php

namespace Drupal\modeler\Plugin\ModelerApiModeler;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler\FormToJsonConverter;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Form\Wrapper;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Plugin implementation of the React Flow Modeler.
 */
#[Modeler(
  id: "workflow_modeler",
  label: new TranslatableMarkup("Workflow modeler"),
  description: new TranslatableMarkup("Workflow modeler built with React Flow.")
)]
class WorkflowModeler extends ModelerBase {

  /**
   * The raw model data as JSON string.
   */
  protected string $rawData = '';

  /**
   * Parsed model components.
   */
  protected array $parsedComponents = [];

  /**
   * Parsed model metadata.
   */
  protected array $metadata = [];

  /**
   * The form-to-JSON converter service.
   */
  protected ?FormToJsonConverter $formToJsonConverter = NULL;

  /**
   * Get the form-to-JSON converter service.
   *
   * The parent ModelerBase declares both its constructor and create() method
   * as final to force modeler plugins into lazy/getter dependency injection
   * (to avoid circular dependencies, see modeler_api issue #3517655). Proper
   * constructor injection is therefore not available; we use the base class's
   * getContainer() getter-injection helper rather than a static service
   * locator.
   */
  protected function getFormToJsonConverter(): FormToJsonConverter {
    if ($this->formToJsonConverter === NULL) {
      $this->formToJsonConverter = $this->getContainer()->get(FormToJsonConverter::class);
    }
    return $this->formToJsonConverter;
  }

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

  /**
   * {@inheritdoc}
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array {
    if ($model->isNew()) {
      $id = '';
      $modelData = $this->prepareEmptyModelData($id);
    }
    else {
      $id = $model->id();
      $modelData = json_encode($this->buildGraph($owner, $model, TRUE), JSON_THROW_ON_ERROR);
    }
    return $this->edit($owner, $id, $modelData, $model->isNew(), $readOnly);
  }

  /**
   * {@inheritdoc}
   */
  public function export(ModelOwnerInterface $owner, ConfigEntityInterface $model): ?string {
    $id = $model->id();
    $version = $owner->getVersion($model);
    $metadata = [
      'label' => $owner->getLabel($model),
      'documentation' => $owner->getDocumentation($model),
      'summary' => $owner->getSummary($model),
      'recipes' => $owner->getRecipes($model),
      'config_actions' => $owner->getConfigActions($model),
      'export_config' => $owner->getExportConfig($model),
      'modules' => $owner->getModules($model),
      'executable' => $owner->getStatus($model),
      'tags' => $owner->getTags($model),
      'changelog' => $owner->getChangelog($model),
      'id' => $id,
      'version' => $version,
      'storage' => $owner->getStorage($model),
      'template' => $owner->getTemplate($model),
    ];

    $configForms = $this->buildConfigForms($owner, $owner->getUsedComponents($model), $id, $model->isNew());

    // The model configuration form speaks its own vocabulary: the machine
    // name lives in model_id, and tags are a single comma-separated string.
    $config = $metadata + ['model_id' => $id];
    $config['tags'] = implode(', ', $metadata['tags']);

    return json_encode([
      'id' => $id,
      'version' => $version,
      'metadata' => $metadata,
      ...$this->buildGraph($owner, $model, FALSE),
      // Cast to an object so that a model with no config forms at all still
      // serializes the map as `{}` instead of as an empty JSON list.
      'configForms' => (object) $configForms,
      'metadataForm' => $this->buildMetadataForm($owner, $config, FALSE),
    ], JSON_THROW_ON_ERROR);
  }

  /**
   * Builds the configuration forms for all plugins used by a model.
   *
   * The exported graph carries each component's configuration values but not
   * the form definitions required to render them. Consumers outside of Drupal
   * therefore receive one form definition per plugin ID, which is all they
   * need as components using the same plugin share their form.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\modeler_api\Component[] $usedComponents
   *   The components used by the model. This covers edge conditions as well as
   *   nodes, as the consumer looks up both by plugin ID.
   * @param string|null $modelId
   *   The ID of the model being exported.
   * @param bool $modelIsNew
   *   Whether the model being exported is new.
   *
   * @return array
   *   The converted configuration forms, keyed by plugin ID.
   */
  protected function buildConfigForms(ModelOwnerInterface $owner, array $usedComponents, ?string $modelId, bool $modelIsNew): array {
    $configForms = [];
    $processed = [];
    foreach ($usedComponents as $component) {
      $pluginId = $component->getPluginId();
      // Annotations carry no plugin, and each plugin is only built once, no
      // matter how many components make use of it.
      if ($pluginId === '' || isset($processed[$pluginId])) {
        continue;
      }
      $processed[$pluginId] = TRUE;
      try {
        $form = $this->buildComponentConfigForm($owner, $component->getType(), $pluginId, $component->getConfiguration(), $modelId, $modelIsNew);
      }
      catch (\Throwable $e) {
        // A single broken plugin must never abort the whole export. The graph
        // itself stays valid, the consumer just cannot render that one form.
        $this->logger->warning('Skipped the config form of plugin %plugin while exporting model %model: @message', [
          '%plugin' => $pluginId,
          '%model' => $modelId ?? '',
          '@message' => $e->getMessage(),
        ]);
        continue;
      }
      if ($form !== NULL) {
        $configForms[$pluginId] = $form;
      }
    }
    return $configForms;
  }

  /**
   * Builds the configuration form for a single component plugin.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param int $componentType
   *   The component type.
   * @param string $pluginId
   *   The plugin ID.
   * @param array $configuration
   *   The plugin configuration.
   * @param string|null $modelId
   *   The ID of the model the form is built for.
   * @param bool $modelIsNew
   *   Whether the model the form is built for is new.
   *
   * @return array|null
   *   The converted configuration form, or NULL if the plugin does not exist
   *   or is not editable.
   */
  protected function buildComponentConfigForm(ModelOwnerInterface $owner, int $componentType, string $pluginId, array $configuration, ?string $modelId, bool $modelIsNew): ?array {
    $plugin = $owner->ownerComponent($componentType, $pluginId);
    if ($plugin === NULL || !$owner->ownerComponentEditable($plugin)) {
      return NULL;
    }
    return $this->buildPluginConfigForm($owner, $plugin, $configuration, $modelId, $modelIsNew);
  }

  /**
   * Converts a plugin's configuration form into its JSON representation.
   *
   * Shared by the config form endpoint and the standalone graph export, so
   * that both always hand out the very same form definition.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin, which must be editable.
   * @param array $configuration
   *   The plugin configuration.
   * @param string|null $modelId
   *   The ID of the model the form is built for.
   * @param bool $modelIsNew
   *   Whether the model the form is built for is new.
   *
   * @return array
   *   The converted configuration form.
   */
  protected function buildPluginConfigForm(ModelOwnerInterface $owner, PluginInspectionInterface $plugin, array $configuration, ?string $modelId, bool $modelIsNew): array {
    if ($plugin instanceof ConfigurableInterface) {
      $plugin->setConfiguration($configuration);
    }
    $form = $owner->buildConfigurationForm($plugin, $modelId, $modelIsNew);
    // The model owner may have added configuration properties to the plugin
    // although it's not configurable. This e.g. happens in ECA for action
    // plugins that have a type property. Let's add the default values
    // separately for those.
    if (!($plugin instanceof ConfigurableInterface)) {
      foreach ($configuration as $configKey => $configValue) {
        if (isset($form[$configKey])) {
          $form[$configKey]['#default_value'] = $configValue;
        }
      }
    }
    $form = $this->formBuilder->getForm(Wrapper::class, $form);
    unset($form['ecatemplatedummy']);
    // Derive the config schema key for this plugin to enable YAML schema
    // discovery on textarea fields.
    $schemaKey = $owner->getPluginSchemaKey($plugin);
    // Convert form to JSON-serializable format.
    return $this->getFormToJsonConverter()->convert($form, $schemaKey);
  }

  /**
   * Builds the model's own configuration form in its JSON representation.
   *
   * The React metadata dialog renders this array with the very same component
   * that renders a plugin's configuration form. Whatever a model owner adds,
   * relabels or hides in the shared modeler_api form therefore reaches the UI
   * without this modeler knowing the individual fields.
   *
   * The YAML hint on the config actions field is applied here rather than in
   * the converter, because the converter derives a widget format from config
   * schema constraints and this form has no config schema behind it. The
   * modeler is the layer that knows its raw JSON keeps config actions as
   * structured data that the user edits as YAML.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param array $config
   *   The values the form fields are built with.
   * @param bool $isNew
   *   Whether the model the form is built for is new.
   *
   * @return array
   *   The converted model configuration form.
   */
  protected function buildMetadataForm(ModelOwnerInterface $owner, array $config, bool $isNew): array {
    $form = $this->defaultModelConfigForm($owner, $config, $isNew);
    // This form carries no plugin, hence no config schema key.
    $fields = $this->getFormToJsonConverter()->convert($form, '');
    $this->markConfigActionsAsYaml($fields);
    return $fields;
  }

  /**
   * Marks the config actions field of a converted form as YAML.
   *
   * The field may sit in any group, so the whole tree is walked.
   *
   * @param array $fields
   *   The converted form fields, modified in place.
   */
  protected function markConfigActionsAsYaml(array &$fields): void {
    foreach ($fields as &$field) {
      if (!is_array($field)) {
        continue;
      }
      if (($field['key'] ?? '') === 'config_actions') {
        $field['format'] = 'yaml';
      }
      if (isset($field['children'])) {
        $this->markConfigActionsAsYaml($field['children']);
      }
    }
  }

  /**
   * Rebuilds the Workflow Modeler graph from the model entity.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model entity.
   * @param bool $includePositions
   *   Whether default coordinates should be included for the editor.
   *
   * @return array{nodes: array, edges: array}
   *   The reconstructed nodes and edges.
   */
  protected function buildGraph(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $includePositions): array {
    $usedComponents = $owner->getUsedComponents($model);
    $nodes = [];
    $edges = [];
    $conditions = [];
    $annotations = [];

    foreach ($owner->getAnnotations($model) as $annotation) {
      foreach ($annotation->getSuccessors() as $association) {
        $annotations[$association->getConditionId()] = $annotation->getLabel();
      }
    }

    // Collect all condition components before building edges. Events are
    // iterated before conditions and may already refer to them.
    foreach ($usedComponents as $component) {
      if ($component->getType() === Api::COMPONENT_TYPE_LINK) {
        $componentId = $component->getId();
        $element = [
          'id' => $componentId,
          'plugin' => $component->getPluginId(),
          'label' => $component->getLabel(),
          'configuration' => $component->getConfiguration(),
          'componentType' => Api::COMPONENT_TYPE_LINK,
          'configured' => TRUE,
        ];
        if ($includePositions) {
          $element['position'] = ['x' => 100, 'y' => 100];
        }
        if (isset($annotations[$componentId])) {
          $element['annotation'] = $annotations[$componentId];
        }
        $conditions[$componentId] = $element;
      }
    }

    // Track duplicate source-target pairs so parallel edges receive unique
    // IDs while the first edge retains the legacy ID format.
    $edgeIdCounts = [];

    foreach ($usedComponents as $component) {
      $componentId = $component->getId();
      $componentType = $component->getType();
      if ($componentType === Api::COMPONENT_TYPE_LINK) {
        continue;
      }

      $element = [
        'id' => $componentId,
        'plugin' => $component->getPluginId(),
        'label' => $component->getLabel(),
        'configuration' => $component->getConfiguration(),
        'componentType' => $componentType,
        'configured' => TRUE,
      ];
      if ($includePositions) {
        $element['position'] = ['x' => 100, 'y' => 100];
      }
      if (isset($annotations[$componentId])) {
        $element['annotation'] = $annotations[$componentId];
      }
      $nodes[] = $element;

      foreach ($component->getSuccessors() as $successor) {
        $successorId = $successor->getId();
        $conditionId = $successor->getConditionId();
        $edgeIdBase = $componentId . '_' . $successorId;
        $count = $edgeIdCounts[$edgeIdBase] ?? 0;
        $edgeId = $count === 0 ? $edgeIdBase : $edgeIdBase . '_' . $count;
        $edgeIdCounts[$edgeIdBase] = $count + 1;

        $edge = [
          'id' => $edgeId,
          'source' => $componentId,
          'target' => $successorId,
        ];
        if ($conditionId && isset($conditions[$conditionId])) {
          $edge['condition'] = $conditions[$conditionId]['plugin'];
          $edge['conditionConfiguration'] = $conditions[$conditionId]['configuration'];
          $edge['conditionLabel'] = $conditions[$conditionId]['label'];
          $edge['conditionId'] = $conditionId;
        }
        if (isset($annotations[$edgeId])) {
          $edge['annotation'] = $annotations[$edgeId];
        }
        $edges[] = $edge;
      }
    }

    return [
      'nodes' => $nodes,
      'edges' => $edges,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    $components = [];
    if (!$readOnly) {
      foreach ($owner->supportedOwnerComponentTypes() as $type => $typeName) {
        foreach ($owner->availableOwnerComponents($type) as $plugin) {
          $definition = $plugin->getPluginDefinition();
          $components[] = [
            'plugin' => $plugin->getPluginId(),
            'provider' => $definition['provider'],
            'label' => $definition['label'] ?? $plugin->getPluginId(),
            'description' => $definition['description'] ?? '',
            'componentType' => $type,
            'documentationUrl' => $owner->pluginDocUrl($plugin, $typeName),
          ];
        }
      }
    }
    // The metadata dialog gets its structure from the form and its values
    // from the React store, which holds the model data the API assembled.
    // The defaults below therefore only have to be of the right type.
    $metadataConfig = [
      'label' => '',
      'model_id' => $id,
      'version' => '',
      'executable' => TRUE,
      'template' => FALSE,
      'storage' => '',
      'documentation' => '',
      'summary' => '',
      'recipes' => [],
      'export_config' => [],
      'modules' => [],
      'config_actions' => [],
      'tags' => '',
      'changelog' => '',
    ];
    $settings = [
      'modelId' => $id,
      'modelData' => $data,
      'components' => $components,
      'typeMap' => Api::COMPONENT_TYPE_NAMES,
      'metadataForm' => $this->buildMetadataForm($owner, $metadataConfig, $isNew),
    ];
    if ($this->request->query->has('select')) {
      $settings['selectComponentId'] = $this->request->query->get('select');
    }
    if (!$readOnly && $this->request->query->has('context')) {
      $settings['selectContextId'] = $this->request->query->get('context');
    }
    if (!$readOnly && $this->request->query->has('contextConfig')) {
      $decoded = json_decode($this->request->query->get('contextConfig'), TRUE);
      // Only accept a decoded array; avoid passing null/scalar downstream.
      if (is_array($decoded)) {
        $settings['setContextConfig'] = $decoded;
      }
    }
    if ($this->request->query->has('hash')) {
      $settings['replayData'] = $owner->getReplayData($this->request->query->get('hash'));
    }
    if ($this->request->headers->has('HX-Request')) {
      $settings['stayInContextOnClose'] = TRUE;
    }
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'workflow-modeler-wrapper',
        'class' => ['workflow-modeler-wrapper'],
      ],
      '#attached' => [
        'library' => ['modeler/react-ui'],
        'drupalSettings' => [
          'modeler' => $settings,
        ],
      ],
      'content' => [
        '#markup' => '<div class="modeler-loading-overlay">
          <div class="modeler-loading-content">
            <div class="modeler-loading-text">Modeler Loading...</div>
          </div>
        </div>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void {
    $this->rawData = $data;
    $this->parsedComponents = [];
    $this->metadata = [];

    // If we have JSON data, parse it and extract components and metadata.
    if (!empty($data)) {
      $decoded = json_decode($data, TRUE);
      if (is_array($decoded)) {
        // Extract metadata.
        if (isset($decoded['metadata'])) {
          $this->metadata = $decoded['metadata'];
          // Ensure ID is also available from the root level.
          if (isset($decoded['id'])) {
            $this->metadata['id'] = $decoded['id'];
          }
        }
        elseif (isset($decoded['id'])) {
          // Fallback: extract metadata from root level for backward
          // compatibility.
          $this->metadata = [
            'id' => $decoded['id'],
            'version' => $decoded['version'] ?? '1.0.0',
            'label' => $decoded['label'] ?? 'Untitled Model',
            'description' => $decoded['description'] ?? '',
            'executable' => $decoded['executable'] ?? TRUE,
            'template' => $decoded['template'] ?? FALSE,
            'storage' => $decoded['storage'] ?? '',
            'documentation' => $decoded['documentation'] ?? '',
            'tags' => $decoded['tags'] ?? [],
            'changelog' => $decoded['changelog'] ?? '',
          ];
        }

        // First, collect all condition components from edges to create them.
        $conditionComponents = [];
        if (isset($decoded['edges'])) {
          foreach ($decoded['edges'] as $edge) {
            if (!empty($edge['condition'])) {
              foreach ($edge['conditionConfiguration'] ?? [] as $key => $value) {
                if (is_bool($value) && $value === FALSE) {
                  unset($edge['conditionConfiguration'][$key]);
                }
              }
              // Preserve the original condition ID if provided, otherwise
              // generate a new one from the edge ID.
              $conditionId = !empty($edge['conditionId'])
                ? $edge['conditionId']
                : $edge['id'] . '_condition';
              $conditionComponents[$conditionId] = new Component(
                $owner,
                $conditionId,
                Api::COMPONENT_TYPE_LINK,
                $edge['condition'],
                $edge['conditionLabel'] ?? $edge['condition'],
                $edge['conditionConfiguration'] ?? [],
              );
            }
          }
        }

        // Create regular node components with their successors.
        $nodeComponents = [];
        if (isset($decoded['nodes'])) {
          foreach ($decoded['nodes'] as $node) {
            // Determine the integer component type: prefer the explicit
            // `componentType` sent by the current frontend, fall back to
            // resolving the legacy string `type` via COMPONENT_TYPE_NAMES.
            $componentType = $node['componentType']
              ?? array_search($node['type'] ?? '', Api::COMPONENT_TYPE_NAMES, TRUE)
              ?: Api::COMPONENT_TYPE_ELEMENT;
            if (isset($node['plugin'])) {
              foreach ($node['configuration'] ?? [] as $key => $value) {
                if (is_bool($value) && $value === FALSE) {
                  unset($node['configuration'][$key]);
                }
              }
              $nodeId = $node['id'] ?? uniqid('component_');
              $nodeComponents[$nodeId] = new Component(
                $owner,
                $nodeId,
                $componentType,
                $node['plugin'],
                $node['label'] ?? $node['plugin'],
                $node['configuration'] ?? [],
              );
            }
          }
        }

        // Build successors from edges.
        if (isset($decoded['edges'])) {
          foreach ($decoded['edges'] as $edge) {
            $sourceId = $edge['source'];
            $targetId = $edge['target'];
            $conditionId = '';

            // If there's a condition, use the original condition ID if
            // provided, otherwise generate one from the edge ID.
            if (!empty($edge['condition'])) {
              $conditionId = !empty($edge['conditionId'])
                ? $edge['conditionId']
                : $edge['id'] . '_condition';
            }

            // Add successor to the source component.
            if (isset($nodeComponents[$sourceId])) {
              $successors = $nodeComponents[$sourceId]->getSuccessors();
              $successors[] = new ComponentSuccessor($targetId, $conditionId);

              // Create new component with updated successors.
              $nodeComponents[$sourceId] = new Component(
                $owner,
                $sourceId,
                $nodeComponents[$sourceId]->getType(),
                $nodeComponents[$sourceId]->getPluginId(),
                $nodeComponents[$sourceId]->getLabel(),
                $nodeComponents[$sourceId]->getConfiguration(),
                $successors,
              );
            }
          }
        }

        // Create annotation components for nodes and edges with annotations.
        $annotationComponents = [];

        // Node annotations.
        if (isset($decoded['nodes'])) {
          foreach ($decoded['nodes'] as $node) {
            if (!empty($node['annotation'])) {
              $annotationId = 'annotation_' . $node['id'];
              $associationId = 'association_' . $node['id'];
              $annotationComponents[$annotationId] = new Component(
                $owner,
                $annotationId,
                Api::COMPONENT_TYPE_ANNOTATION,
                '',
                $node['annotation'],
                [],
                [new ComponentSuccessor($associationId, $node['id'])],
              );
            }
          }
        }

        // Edge annotations.
        if (isset($decoded['edges'])) {
          foreach ($decoded['edges'] as $edge) {
            if (!empty($edge['annotation'])) {
              $annotationId = 'annotation_' . $edge['id'];
              $associationId = 'association_' . $edge['id'];
              $annotationComponents[$annotationId] = new Component(
                $owner,
                $annotationId,
                Api::COMPONENT_TYPE_ANNOTATION,
                '',
                $edge['annotation'],
                [],
                [new ComponentSuccessor($associationId, $edge['id'])],
              );
            }
          }
        }

        // Combine all components.
        $this->parsedComponents = array_merge(
          array_values($nodeComponents),
          array_values($conditionComponents),
          array_values($annotationComponents)
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData(): string {
    return $this->rawData;
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(): array {
    return $this->parsedComponents;
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(ModelOwnerInterface $owner): JsonResponse {
    $data = [];

    // Get JSON data from request body.
    $json_data = json_decode($this->request->getContent(), TRUE);
    if (!$json_data) {
      $data['error'] = $this->t('Invalid data.');
    }
    else {
      $component_type = $json_data['component_type'] ?? NULL;
      $model_id = $json_data['model_id'] ?? NULL;
      $model_is_new = $json_data['is_new'] ?? FALSE;
      $plugin_id = $json_data['plugin_id'] ?? NULL;
      $configuration = $json_data['configuration'] ?? [];
      if (!$component_type || !$plugin_id) {
        $data['error'] = $this->t('Invalid JSON data.');
      }
      else {
        $plugin = $owner->ownerComponent($component_type, $plugin_id);
        if (!$plugin) {
          $data['error'] = $this->t('Invalid plugin.');
        }
        elseif (!$owner->ownerComponentEditable($plugin)) {
          $data['error'] = $this->t('Plugin not editable.');
        }
        else {
          $data['form'] = $this->buildPluginConfigForm($owner, $plugin, $configuration, $model_id, $model_is_new);
        }
      }
    }
    return new JsonResponse($data);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEmptyModelData(string &$id): string {
    $id = $this->generateId();

    $emptyModel = [
      'id' => $id,
      'metadata' => [
        'version' => '1.0.0',
        'label' => 'New Model',
        'documentation' => '',
        'executable' => TRUE,
        'template' => FALSE,
        'storage' => '',
        'tags' => [],
        'changelog' => '',
      ],
      'nodes' => [],
      'edges' => [],
    ];

    return json_encode($emptyModel, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->metadata['id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->metadata['label'] ?? 'Untitled Model';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    $tags = $this->metadata['tags'] ?? [];
    return is_array($tags) ? $tags : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return $this->metadata['changelog'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate(): bool {
    return (bool) ($this->metadata['template'] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(): string {
    return $this->metadata['storage'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return $this->metadata['documentation'] ?? '';
  }

  /**
   * {@inheritdoc}
   *
   * Unlike the getters above, an absent key yields NULL rather than an empty
   * value. NULL tells the API that this model's raw data does not express the
   * setting at all, so the owner's current value is left alone; an empty string
   * is the user deliberately clearing the field and does overwrite it.
   */
  public function getSummary(): ?string {
    return array_key_exists('summary', $this->metadata) ? (string) $this->metadata['summary'] : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Returns NULL for an absent key, for the reason given on getSummary().
   */
  public function getRecipes(): ?array {
    if (!array_key_exists('recipes', $this->metadata)) {
      return NULL;
    }
    $recipes = $this->metadata['recipes'];
    return is_array($recipes) ? $recipes : [];
  }

  /**
   * {@inheritdoc}
   *
   * Returns NULL for an absent key, for the reason given on getSummary().
   */
  public function getConfigActions(): ?array {
    if (!array_key_exists('config_actions', $this->metadata)) {
      return NULL;
    }
    $configActions = $this->metadata['config_actions'];
    return is_array($configActions) ? $configActions : [];
  }

  /**
   * {@inheritdoc}
   *
   * Returns NULL for an absent key, for the reason given on getSummary().
   */
  public function getExportConfig(): ?array {
    if (!array_key_exists('export_config', $this->metadata)) {
      return NULL;
    }
    $exportConfig = $this->metadata['export_config'];
    return is_array($exportConfig) ? $exportConfig : [];
  }

  /**
   * {@inheritdoc}
   *
   * Returns NULL for an absent key, for the reason given on getSummary().
   */
  public function getModules(): ?array {
    if (!array_key_exists('modules', $this->metadata)) {
      return NULL;
    }
    $modules = $this->metadata['modules'];
    return is_array($modules) ? $modules : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return (bool) ($this->metadata['executable'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->metadata['version'] ?? '1.0.0';
  }

  /**
   * {@inheritdoc}
   */
  public function enable(ModelOwnerInterface $owner): static {
    $this->metadata['executable'] = TRUE;
    $this->rebuildRawData();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(ModelOwnerInterface $owner): static {
    $this->metadata['executable'] = FALSE;
    $this->rebuildRawData();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): static {
    $this->metadata['id'] = $id;
    $this->metadata['label'] = $label;
    $this->rebuildRawData();
    return $this;
  }

  /**
   * Rebuilds the raw JSON data from the current metadata.
   *
   * Decodes the existing raw data, applies the current metadata and ID,
   * and re-encodes the result. This ensures rawData stays in sync with
   * metadata after any modification.
   */
  protected function rebuildRawData(): void {
    $decoded = json_decode($this->rawData, TRUE);
    if (!is_array($decoded)) {
      $decoded = [];
    }
    $decoded['id'] = $this->metadata['id'] ?? '';
    $decoded['metadata'] = $this->metadata;
    unset($decoded['metadata']['id']);
    $this->rawData = json_encode($decoded, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
  }

}
