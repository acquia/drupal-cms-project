<?php

namespace Drupal\modeler_api;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\ManagedStorage;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token as BaseToken;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ContextPluginManager;
use Drupal\modeler_api\Plugin\DependencyPluginManager;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drupal\modeler_api\Plugin\TemplateTokenPluginManager;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Provides services of the modeler API.
 */
class Api {

  use StringTranslationTrait;

  /**
   * Error messages that get collected through data preparation.
   *
   * @var string[]
   */
  protected array $errors = [];

  /**
   * Gets the API service.
   *
   * @return \Drupal\modeler_api\Api
   *   The API service.
   */
  public static function get(): Api {
    return \Drupal::service('modeler_api.service');
  }

  public const int COMPONENT_TYPE_START = 1;
  public const int COMPONENT_TYPE_SUBPROCESS = 2;
  public const int COMPONENT_TYPE_SWIMLANE = 3;
  public const int COMPONENT_TYPE_ELEMENT = 4;
  public const int COMPONENT_TYPE_LINK = 5;
  public const int COMPONENT_TYPE_GATEWAY = 6;
  public const int COMPONENT_TYPE_ANNOTATION = 7;

  public const array AVAILABLE_COMPONENT_TYPES = [
    self::COMPONENT_TYPE_START,
    self::COMPONENT_TYPE_SUBPROCESS,
    self::COMPONENT_TYPE_SWIMLANE,
    self::COMPONENT_TYPE_ELEMENT,
    self::COMPONENT_TYPE_LINK,
    self::COMPONENT_TYPE_GATEWAY,
    self::COMPONENT_TYPE_ANNOTATION,
  ];

  /**
   * Maps component type constants to their YAML key names.
   */
  public const array COMPONENT_TYPE_NAMES = [
    self::COMPONENT_TYPE_START => 'start',
    self::COMPONENT_TYPE_SUBPROCESS => 'subprocess',
    self::COMPONENT_TYPE_SWIMLANE => 'swimlane',
    self::COMPONENT_TYPE_ELEMENT => 'element',
    self::COMPONENT_TYPE_LINK => 'link',
    self::COMPONENT_TYPE_GATEWAY => 'gateway',
    self::COMPONENT_TYPE_ANNOTATION => 'annotation',
  ];

  /**
   * Constructs the modeler API plugin manager.
   */
  public function __construct(
    protected AccountProxy $currentUser,
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected ModelerPluginManager $modelerPluginManager,
    protected ConfigFactoryInterface $configFactory,
    protected ManagedStorage $configStorage,
    protected FileSystemInterface $fileSystem,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected ContextPluginManager $contextPluginManager,
    protected DependencyPluginManager $dependencyPluginManager,
    protected TemplateTokenPluginManager $templateTokenPluginManager,
    protected ContextListBuilder $contextListBuilder,
    protected DependencyListBuilder $dependencyListBuilder,
    protected TemplateTokenListBuilder $templateTokenListBuilder,
    protected \Closure $entityTypeManagerFactory,
    protected \Closure $routeProviderFactory,
    protected \Closure $tokenFactory,
    protected ?\Closure $tokenTreeBuilderFactory = NULL,
  ) {}

  /**
   * Get the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return ($this->entityTypeManagerFactory)();
  }

  /**
   * Get the route provider service.
   *
   * @return \Drupal\Core\Routing\RouteProviderInterface
   *   The route provider service.
   */
  protected function getRouteProvider(): RouteProviderInterface {
    return ($this->routeProviderFactory)();
  }

  /**
   * Get the token service.
   *
   * @return \Drupal\Core\Utility\Token
   *   The token service.
   */
  protected function getTokenService(): BaseToken {
    return ($this->tokenFactory)();
  }

  /**
   * Get the token tree builder.
   *
   * @return mixed|null
   *   The token tree builder if available.
   */
  protected function getTokenTreeBuilder(): mixed {
    return is_callable($this->tokenTreeBuilderFactory) ? ($this->tokenTreeBuilderFactory)() : NULL;
  }

  /**
   * Get the modeler if there's only one available, except the fallback.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface|null
   *   The modeler if only one exists besides the fallback, NULL otherwise.
   */
  public function getModeler(): ?ModelerInterface {
    $modelers = $this->modelerPluginManager->getAllInstances();
    if (count($modelers) === 2) {
      unset($modelers['fallback']);
      return reset($modelers);
    }
    return NULL;
  }

  /**
   * Finds the model owner plugin of a config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|string $model
   *   The config entity or the config entity type ID.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface|null
   *   The model owner
   */
  public function findOwner(ConfigEntityInterface|string $model): ?ModelOwnerInterface {
    $entityTypeId = is_string($model) ? $model : $model->getEntityTypeId();
    foreach ($this->modelOwnerPluginManager->getAllInstances() as $owner) {
      if ($entityTypeId === $owner->configEntityTypeId()) {
        return $owner;
      }
    }
    return NULL;
  }

  /**
   * Embeds the modeler into a config entity form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model entity.
   * @param string $default_modeler_id
   *   The default modeler ID.
   *
   * @return array
   *   The form element describing the integrated modeler.
   */
  public function embedIntoForm(array &$form, FormStateInterface $form_state, ConfigEntityInterface $model, string $default_modeler_id): array {
    $owner = $this->findOwner($model);
    if ($model->isNew()) {
      $owner->setModelerId($model, $default_modeler_id);
      $id = '';
      $modelerData = $owner->getModeler($model)->prepareEmptyModelData($id);
    }
    else {
      $modelerData = $form_state->getUserInput()['modeler_api_data'] ?? '';
    }
    if ($modelerData) {
      $owner->setModelData($model, $modelerData);
    }
    $element = $this->edit($model, $default_modeler_id);
    unset($element['form']);
    $form['#attributes']['class'][] = 'modeler-api-embed';
    $form['#validate'][] = [$this, 'validateEmbed'];
    $form['#entity_builders'][] = [$this, 'buildEntity'];
    return $element;
  }

  /**
   * Validate callback for forms that contain an embedded modeler.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state.
   */
  public function validateEmbed(array &$form, FormStateInterface $form_state): void {
    $modelerData = $form_state->getValue('modeler_api_data');
    $form_state->set('modeler_api_data', $modelerData);
    $formObject = $form_state->getFormObject();
    if ($formObject instanceof EntityFormInterface) {
      $entity = $formObject->getEntity();
      if ($entity instanceof ConfigEntityInterface) {
        $owner = $this->findOwner($entity);
        if (!$this->prepareModelFromData($modelerData, $owner->getPluginId(), 'bpmn_io', $entity->isNew(), TRUE, $entity)) {
          $form_state->setError($form, implode('<br/>', $this->getErrors()));
        }
      }
    }
  }

  /**
   * Entity builder callback for forms that contain an embedded modeler.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state.
   */
  public function buildEntity(string $entity_type_id, ConfigEntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if ($form['#validated'] ?? FALSE) {
      $owner = $this->findOwner($entity);
      $this->prepareModelFromData($form_state->get('modeler_api_data'), $owner->getPluginId(), 'bpmn_io', $entity->isNew(), TRUE, $entity);
    }
  }

  /**
   * Edit the given entity if the modeler supports that.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The config entity.
   * @param string|null $modelerId
   *   The optional ID of the modeler that should be used for editing.
   * @param bool $readOnly
   *   TRUE, if the model should only be viewed, FALSE otherwise.
   *
   * @return array
   *   The render array for editing the entity.
   */
  public function edit(ConfigEntityInterface $model, ?string $modelerId = NULL, bool $readOnly = FALSE): array {
    $owner = $this->findOwner($model);
    if (!$owner->isEditable($model)) {
      return [];
    }

    if ($modelerId === NULL) {
      // If there's only 1 modeler, let's use that.
      $modeler = $owner->getModeler($model);
      if (!$modeler || $modeler->getPluginId() === 'fallback') {
        $modelerId = $this->getModeler()?->getPluginId();
      }
    }

    if ($modelerId !== NULL && $modelerId !== $owner->getModelerId($model)) {
      try {
        $modeler = $this->modelerPluginManager->createInstance($modelerId);
      }
      catch (PluginException $e) {
        return [
          '#markup' => $this->t('This modeler can not be loaded: :msg.', [
            ':msg' => $e->getMessage(),
          ]),
        ];
      }
      $data = '';
    }
    else {
      $modeler = $owner->getModeler($model);
      $data = $owner->getModelData($model);
    }
    if (!$modeler->isEditable()) {
      return [
        '#markup' => $this->t('This model can not be edited with this modeler.'),
      ];
    }
    if ($data === '' || $owner->getModeler($model)->getPluginId() !== $modeler->getPluginId()) {
      // No raw data is available, or model is from a different modeler, so
      // let the modeler do upstream conversion.
      $build = $modeler->convert($owner, $model, $readOnly);
    }
    else {
      $build = $modeler->edit($owner, $model->id() ?? 'placeholder', $data, $model->isNew(), $readOnly);
    }
    // Add settings.
    $settings = [
      'metadata' => [
        'version' => $owner->getVersion($model),
        'label' => $owner->getLabel($model),
        'documentation' => $owner->getDocumentation($model),
        'storage' => $owner->getStorage($model),
        'executable' => $owner->getStatus($model),
        'template' => $owner->getTemplate($model),
        'tags' => $owner->getTags($model),
        'changelog' => $owner->getChangelog($model),
      ],
      'component_labels' => $owner->componentLabels(),
      'component_labels_plural' => $owner->componentLabelsPlural(),
      'model_constraints' => $this->prepareModelConstraints($owner),
      'permissions' => ModelerApiPermissions::userPermissionsForModeler($this->currentUser, $owner->getPluginId()),
      'favorite_components' => $owner->favoriteOwnerComponents(),
      'global_tokens_url' => Url::fromRoute('modeler_api.global_tokens')->toString(),
      'template_tokens_url' => Url::fromRoute('modeler_api.template_tokens', [
        'owner_id' => $owner->getPluginId(),
      ])->toString(),
      'contexts' => $this->contextListBuilder->getList($owner->getPluginId()),
      'dependencies' => $this->dependencyListBuilder->getList($owner->getPluginId()),
      'readOnly' => $readOnly,
      'isNew' => $model->isNew(),
    ];
    $modelType = $owner->configEntityTypeId();
    if ($owner->configEntityBasePath() !== NULL) {
      $settings += [
        'save_url' => Url::fromRoute('entity.' . $modelType . '.save', [
          'modeler_id' => $modeler->getPluginId(),
        ])->toString(),
        'token_url' => Url::fromRoute('system.csrftoken')->toString(),
        'collection_url' => Url::fromRoute('entity.' . $modelType . '.collection')->toString(),
      ];
    }
    else {
      $build['modeler_api_data'] = [
        '#type' => 'hidden',
      ];
    }
    $settings['mode'] = 'edit';
    $settings['config_url'] = Url::fromRoute('entity.' . $modelType . '.config', [
      'modeler_id' => $modeler->getPluginId(),
    ])->toString();
    if ($owner->supportsReplayData()) {
      $settings['replay_url'] = Url::fromRoute('entity.' . $modelType . '.replay', [
        'modeler_id' => $modeler->getPluginId(),
      ])->toString();
    }
    if ($owner->supportsTesting()) {
      $settings['test_url'] = Url::fromRoute('entity.' . $modelType . '.test', [
        'modeler_id' => $modeler->getPluginId(),
      ])->toString();
    }
    if (!$model->isNew() && $owner->isExportable($model)) {
      $settings['export_url'] = Url::fromRoute('entity.' . $modelType . '.export', [
        $modelType => $model->id(),
      ])->toString();
      $settings['export_recipe_url'] = Url::fromRoute('entity.' . $modelType . '.export_recipe', [
        $modelType => $model->id(),
      ])->toString();
    }
    $build['#attached']['drupalSettings']['modeler_api'] = $settings;
    $build['#title'] = $this->t(':type Model: :label', [':type' => $owner->label(), ':label' => $model->label()]);
    $build['config_form'] = [
      '#type' => 'container',
      '#id' => 'modeler-api-config-form',
    ];
    return $build;
  }

  /**
   * View the given entity if the modeler supports that.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The config entity.
   * @param string|null $modelerId
   *   The optional ID of the modeler that should be used for viewing.
   *
   * @return array
   *   The render array for viewing the entity.
   */
  public function view(ConfigEntityInterface $model, ?string $modelerId = NULL): array {
    $build = $this->edit($model, $modelerId, TRUE);
    $build['#attached']['drupalSettings']['modeler_api']['mode'] = 'view';
    return $build;
  }

  /**
   * Parses the raw modeler data and creates/updates the model entity.
   *
   * @param string $data
   *   The raw model data.
   * @param string $model_owner_id
   *   The model owner ID.
   * @param string $modeler_id
   *   The modeler ID.
   * @param bool $isNew
   *   TRUE, if the data is for a new model, FALSE if we'd expect the model to
   *   already exist.
   * @param bool $dry_run
   *   If TRUE, the method will always create a new entity and avoid saving
   *   the optional raw data.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model
   *   The optional model entity to be used.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|null
   *   The prepared model entity, if all is successful, NULL otherwise.
   */
  public function prepareModelFromData(string $data, string $model_owner_id, string $modeler_id, bool $isNew, bool $dry_run = FALSE, ?ConfigEntityInterface $model = NULL): ?ConfigEntityInterface {
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->modelOwnerPluginManager->createInstance($model_owner_id);
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface $modeler */
    $modeler = $this->modelerPluginManager->createInstance($modeler_id);

    $this->errors = [];
    $modeler->parseData($owner, $data);
    $modelId = mb_strtolower($modeler->getId());

    // Validate the model ID that it doesn't exist yet for new models.
    if ($isNew && call_user_func($owner->modelIdExistsCallback(), $modelId)) {
      $this->errors[] = 'The model ID already exists.';
      return NULL;
    }

    if ($model !== NULL) {
      $model->setOriginal(clone $model);
      $owner->resetComponents($model);
    }
    else {
      $storage = $this->getEntityTypeManager()->getStorage($owner->configEntityTypeId());
      if ($dry_run) {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
        $model = $storage->create(['id' => $modelId]);
      }
      else {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
        $model = $storage->load($modelId);
        if ($model) {
          $model->setOriginal(clone $model);
          $owner->resetComponents($model);
        }
        else {
          /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
          $model = $storage->create(['id' => $modelId]);
        }
      }
    }
    if ($owner->supportsStatus()) {
      $owner->setStatus($model, $modeler->getStatus());
    }
    if ($owner->supportsTemplate()) {
      $owner->setTemplate($model, $modeler->getTemplate());
    }
    $owner
      ->setModelerId($model, $modeler_id)
      ->setChangelog($model, $modeler->getChangelog())
      ->setLabel($model, $modeler->getLabel())
      ->setStorage($model, $modeler->getStorage())
      ->setDocumentation($model, $modeler->getDocumentation())
      ->setTags($model, $modeler->getTags())
      ->setVersion($model, $modeler->getVersion());
    $annotations = [];
    $colors = [];
    $swimlanes = [];
    $componentTypeCounts = [];
    // Track successor counts per component for successor constraints.
    // Key: component type, Value: array of successor counts per component.
    $successorCountsByType = [];
    foreach ($modeler->readComponents() as $component) {
      if ($color = $component->getColor()) {
        $colors[$component->getId()] = $color;
      }
      if ($id = $component->getParentId()) {
        if (!isset($swimlanes[$id])) {
          $swimlanes[$id] = [
            'id' => NULL,
            'name' => '',
            'components' => [],
          ];
        }
        if ($component->getType() === self::COMPONENT_TYPE_SWIMLANE) {
          $swimlanes[$id]['id'] = $component->getId();
          $swimlanes[$id]['name'] = $component->getLabel();
          continue;
        }
        $swimlanes[$id]['components'][] = $component->getId();
      }
      if ($component->getType() === self::COMPONENT_TYPE_ANNOTATION) {
        $annotations[] = $component;
        continue;
      }
      // Count components by type for cardinality constraint validation.
      $type = $component->getType();
      $componentTypeCounts[$type] = ($componentTypeCounts[$type] ?? 0) + 1;
      $successorCountsByType[$type][] = [
        'id' => $component->getId(),
        'label' => $component->getLabel(),
        'count' => count($component->getSuccessors()),
      ];
      if ($errors = $component->validate()) {
        $this->errors = array_merge($this->errors, $errors);
      }
      if (!$owner->addComponent($model, $component)) {
        $this->errors[] = 'A component can not be added.';
      }
    }
    // Validate model-level cardinality constraints.
    $this->validateModelConstraints($owner, $componentTypeCounts, $successorCountsByType);
    $owner->setAnnotations($model, $annotations);
    $owner->setColors($model, $colors);
    $owner->setSwimlanes($model, $swimlanes);
    if (empty($this->errors)) {
      if (!$dry_run) {
        $owner->finalizeAddingComponents($model);
        $owner->setModelData($model, $data);
      }
      return $model;
    }
    $this->errors = array_unique($this->errors);
    return NULL;
  }

  /**
   * Prepares model constraints for the frontend.
   *
   * Translates integer component type keys to string names that the
   * frontend understands (e.g., 'start', 'element', 'gateway').
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   *
   * @return array<string, array{min?: int, max?: int}>
   *   Constraints keyed by component type name string.
   */
  protected function prepareModelConstraints(ModelOwnerInterface $owner): array {
    $constraints = $owner->modelConstraints();
    if (empty($constraints)) {
      return [];
    }
    $result = [];
    foreach ($constraints as $type => $constraint) {
      $typeName = self::COMPONENT_TYPE_NAMES[$type] ?? NULL;
      if ($typeName !== NULL) {
        $result[$typeName] = $constraint;
      }
    }
    return $result;
  }

  /**
   * Validates model-level cardinality constraints.
   *
   * Checks the component counts per type and per-component successor counts
   * against the constraints declared by the model owner.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param array<int, int> $componentTypeCounts
   *   Component counts keyed by component type constant.
   * @param array<int, array<int, array{id: string, label: string, count: int}>> $successorCountsByType
   *   Per-type list of component successor info.
   */
  protected function validateModelConstraints(ModelOwnerInterface $owner, array $componentTypeCounts, array $successorCountsByType): void {
    $constraints = $owner->modelConstraints();
    if (empty($constraints)) {
      return;
    }
    $labels = $owner->componentLabels();
    $labelsPlural = $owner->componentLabelsPlural();
    foreach ($constraints as $type => $constraint) {
      $count = $componentTypeCounts[$type] ?? 0;
      $typeName = self::COMPONENT_TYPE_NAMES[$type] ?? 'unknown';
      $label = $labels[$typeName] ?? $typeName;
      $labelPlural = $labelsPlural[$typeName] ?? $label . 's';
      if (isset($constraint['min']) && $count < $constraint['min']) {
        $this->errors[] = $constraint['min'] === 1
          ? (string) $this->t('A model requires at least one @label.', [
            '@label' => $label,
          ])
          : (string) $this->t('A model requires at least @min @label_plural.', [
            '@min' => $constraint['min'],
            '@label_plural' => $labelPlural,
          ]);
      }
      if (isset($constraint['max']) && $count > $constraint['max']) {
        $this->errors[] = $constraint['max'] === 1
          ? (string) $this->t('A model allows at most one @label.', [
            '@label' => $label,
          ])
          : (string) $this->t('A model allows at most @max @label_plural.', [
            '@max' => $constraint['max'],
            '@label_plural' => $labelPlural,
          ]);
      }
      // Validate successor cardinality per component.
      if (isset($constraint['successors']) && isset($successorCountsByType[$type])) {
        $sConstraint = $constraint['successors'];
        foreach ($successorCountsByType[$type] as $info) {
          if (isset($sConstraint['min']) && $info['count'] < $sConstraint['min']) {
            $this->errors[] = (string) $this->t('@label "@name" requires at least @min successor(s).', [
              '@label' => $label,
              '@name' => $info['label'],
              '@min' => $sConstraint['min'],
            ]);
          }
          if (isset($sConstraint['max']) && $info['count'] > $sConstraint['max']) {
            $this->errors[] = $sConstraint['max'] === 0
              ? (string) $this->t('@label "@name" must not have any successors.', [
                '@label' => $label,
                '@name' => $info['label'],
              ])
              : (string) $this->t('@label "@name" allows at most @max successor(s).', [
                '@label' => $label,
                '@name' => $info['label'],
                '@max' => $sConstraint['max'],
              ]);
          }
        }
      }
    }
  }

  /**
   * Exports the model owner's config with all dependencies into an archive.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The model owner's config entity.
   * @param string|null $archiveFileName
   *   The fully qualified filename for the archive. If NULL, this only
   *   calculates and returns the dependencies but doesn't write an archive.
   *
   * @return array
   *   An array with "config" and "module" keys, each containing the list of
   *   dependencies.
   */
  public function exportArchive(ModelOwnerInterface $owner, ConfigEntityInterface $entity, ?string $archiveFileName = NULL): array {
    $dependencies = [
      'config' => [
        $owner->configEntityProviderId() . '.' . $owner->configEntityTypeId() . '.' . $entity->id(),
      ],
      'module' => [],
    ];
    if ($owner->storageMethod($entity) === Settings::STORAGE_OPTION_SEPARATE) {
      $dependencies['config'][] = 'modeler_api.data_model.' . $owner->storageId($entity);
    }
    $this->getNestedDependencies($dependencies, $entity->getDependencies());
    if ($archiveFileName !== NULL) {
      if (file_exists($archiveFileName)) {
        try {
          @$this->fileSystem->delete($archiveFileName);
        }
        catch (FileException) {
          // Ignore failed deletes.
        }
      }
      $archiver = new ArchiveTar($archiveFileName, 'gz');
      foreach ($dependencies['config'] as $name) {
        $config = $this->configStorage->read($name);
        if ($config) {
          unset($config['uuid'], $config['_core']);
          $archiver->addString("$name.yml", Yaml::encode($config));
        }
      }
      $archiver->addString('dependencies.yml', Yaml::encode($dependencies));
    }

    // Remove model from the config dependencies.
    array_shift($dependencies['config']);
    foreach ($dependencies as $type => $values) {
      if (empty($values)) {
        unset($dependencies[$type]);
      }
      else {
        sort($dependencies[$type]);
      }
    }
    return $dependencies;
  }

  /**
   * Recursively determines config dependencies.
   *
   * @param array $allDependencies
   *   The list of all dependencies.
   * @param array $dependencies
   *   The list of dependencies to be added.
   */
  public function getNestedDependencies(array &$allDependencies, array $dependencies): void {
    foreach ($dependencies['module'] ?? [] as $module) {
      if (!in_array($module, $allDependencies['module'], TRUE)) {
        $allDependencies['module'][] = $module;
      }
    }
    if (empty($dependencies['config'])) {
      return;
    }
    foreach ($dependencies['config'] as $dependency) {
      if (!in_array($dependency, $allDependencies['config'], TRUE)) {
        $allDependencies['config'][] = $dependency;
        $depConfig = $this->configFactory->get($dependency)->getStorage()->read($dependency);
        if ($depConfig && isset($depConfig['dependencies'])) {
          $this->getNestedDependencies($allDependencies, $depConfig['dependencies']);
        }
      }
    }
  }

  /**
   * Provides a list of available plugins from the owner for a given type.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param int $type
   *   The component type.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   The list of plugins.
   */
  public function availableOwnerComponents(ModelOwnerInterface $owner, int $type): array {
    assert(in_array($type, self::AVAILABLE_COMPONENT_TYPES), 'Invalid component type');
    return $owner->availableOwnerComponents($type);
  }

  /**
   * Gets all available contexts.
   *
   * @return \Drupal\modeler_api\Context[]
   *   The list of all contexts, keyed by context ID.
   */
  public function getContexts(): array {
    return $this->contextPluginManager->getAllContexts();
  }

  /**
   * Gets a single context by its ID.
   *
   * @param string $id
   *   The context ID.
   *
   * @return \Drupal\modeler_api\Context|null
   *   The context, or NULL if not found.
   */
  public function getContext(string $id): ?Context {
    return $this->contextPluginManager->getContext($id);
  }

  /**
   * Gets all contexts for a given model owner.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface|string $owner
   *   The model owner plugin instance or its ID.
   *
   * @return \Drupal\modeler_api\Context[]
   *   The list of contexts for the given model owner, keyed by context ID.
   */
  public function getContextsByModelOwner(ModelOwnerInterface|string $owner): array {
    $ownerId = is_string($owner) ? $owner : $owner->getPluginId();
    return $this->contextPluginManager->getContextsByModelOwner($ownerId);
  }

  /**
   * Gets all dependency definitions.
   *
   * @return \Drupal\modeler_api\Dependency[]
   *   The list of all dependency definitions, keyed by dependency ID.
   */
  public function getDependencies(): array {
    return $this->dependencyPluginManager->getAllDependencies();
  }

  /**
   * Gets a single dependency definition by its ID.
   *
   * @param string $id
   *   The dependency ID.
   *
   * @return \Drupal\modeler_api\Dependency|null
   *   The dependency, or NULL if not found.
   */
  public function getDependency(string $id): ?Dependency {
    return $this->dependencyPluginManager->getDependency($id);
  }

  /**
   * Gets all dependency definitions for a given model owner.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface|string $owner
   *   The model owner plugin instance or its ID.
   *
   * @return \Drupal\modeler_api\Dependency[]
   *   The list of dependencies for the given model owner, keyed by ID.
   */
  public function getDependenciesByModelOwner(ModelOwnerInterface|string $owner): array {
    $ownerId = is_string($owner) ? $owner : $owner->getPluginId();
    return $this->dependencyPluginManager->getDependenciesByModelOwner($ownerId);
  }

  /**
   * Gets error messages that got collected through data preparation.
   *
   * @return string[]
   *   The error messages that got collected through data preparation.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Gets the route, if it exists, NULL otherwise.
   *
   * @param string $name
   *   The route name.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The route, if it exists, NULL otherwise.
   */
  public function getRouteByName(string $name): ?Route {
    try {
      return $this->getRouteProvider()->getRouteByName($name);
    }
    catch (RouteNotFoundException) {
      // If the route can not be found, don't set the configure route.
    }
    return NULL;
  }

  /**
   * Gets the menu name of the parent path.
   *
   * @param string $path
   *   The path of which we search for the parent path.
   *
   * @return string|null
   *   The menu name of the parent path, if we can find it, FALSE otherwise.
   */
  public function getParentMenuName(string $path): ?string {
    $parts = explode('/', trim($path, '/'));
    array_pop($parts);
    $path = implode('/', $parts);
    $url = Url::fromUri('internal:/' . $path);
    $links = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters());
    if (!empty($links)) {
      $menuLink = reset($links);
      return $menuLink->getPluginId();
    }
    return NULL;
  }

  /**
   * Get the edit URL for an entity of given type and id.
   *
   * @param string $type
   *   The entity type.
   * @param string $id
   *   The entity id.
   *
   * @return \Drupal\Core\Url
   *   The edit URL.
   */
  public function editUrl(string $type, string $id): Url {
    $name = 'entity.' . $type . '.edit';
    if (!$this->getRouteByName($name)) {
      $name = 'entity.' . $type . '.edit_form';
      if (!$this->getRouteByName($name)) {
        return Url::fromRoute('entity.' . $type . '.collection');
      }
    }
    return Url::fromRoute($name, [$type => $id]);
  }

  /**
   * Prepares the global tokens for the modeler.
   *
   * @return array
   *   The global tokens.
   */
  public function prepareGlobalTokens(): array {
    $treeBuilder = $this->getTokenTreeBuilder();
    if ($treeBuilder === NULL) {
      return [];
    }
    // If the token tree builder is available, the token service will be the
    // one from contrib module. But we don't declare the dependency.
    $tokenService = $this->getTokenService();

    $tokens = [];
    try {
      $tokenInfo = $tokenService->getInfo();
    }
    catch (\Throwable) {
      return [];
    }
    // @phpstan-ignore-next-line
    foreach ($tokenService->getGlobalTokenTypes() as $type) {
      try {
        $tree = $treeBuilder->buildTree($type);
      }
      catch (\Throwable) {
        // Skip this token type to prevent WSOD.
        continue;
      }
      $tokens[$type] = [
        'name' => $tokenInfo['types'][$type]['name'] ?? (string) $type,
        'children' => [],
      ];
      foreach ($tree as $token => $def) {
        try {
          $tokens[$type]['children'][$token] = $this->prepareTokenDefinition($def, $tokenService);
        }
        catch (\Throwable) {
          // Skip this single token to prevent WSOD.
          continue;
        }
      }
    }
    return $tokens;
  }

  /**
   * Prepares the template tokens for the modeler.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   *
   * @return array
   *   The template tokens.
   */
  public function prepareTemplateTokens(ModelOwnerInterface $owner): array {
    if (!$owner->supportsTemplate()) {
      return [];
    }
    return $this->templateTokenListBuilder->getList($owner->getPluginId());
  }

  /**
   * Recursively prepare token definitions by resolving its value and children.
   *
   * @param array $def
   *   The token definitions.
   * @param \Drupal\Core\Utility\Token $tokenService
   *   The token service.
   *
   * @return array
   *   The token definitions.
   */
  private function prepareTokenDefinition(array $def, BaseToken $tokenService): array {
    if (empty($def['children'])) {
      try {
        $def['value'] = $tokenService->replace($def['raw token'] ?? '', [], ['clear' => TRUE]);
      }
      catch (\Throwable) {
        // Ignore this single token replacement to prevent WSOD.
        $def['value'] = '';
      }
    }
    else {
      foreach ($def['children'] as $childToken => $childDef) {
        try {
          $def['children'][$childToken] = $this->prepareTokenDefinition($childDef, $tokenService);
        }
        catch (\Throwable) {
          // Skip this single child token to prevent WSOD.
          unset($def['children'][$childToken]);
        }
      }
    }
    return $def;
  }

}
