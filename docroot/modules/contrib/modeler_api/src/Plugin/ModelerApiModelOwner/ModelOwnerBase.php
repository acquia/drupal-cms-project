<?php

namespace Drupal\modeler_api\Plugin\ModelerApiModelOwner;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentColor;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Entity\DataModel;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for model owner plugins.
 *
 * The constructor and the create method are declared final on purpose, as
 * implementing plugins should not use dependency injection, as that would lead
 * towards circular dependencies.
 */
abstract class ModelOwnerBase extends PluginBase implements ModelOwnerInterface {

  /**
   * {@inheritdoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Api $api,
    protected ModelerPluginManager $modelerPluginManager,
    protected UuidInterface $uuidGenerator,
    protected TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('modeler_api.service'),
      $container->get('plugin.manager.modeler_api.modeler'),
      $container->get('uuid'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  final public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  final public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function componentLabels(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function componentLabelsPlural(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function modelConstraints(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function modelConfigFormAlter(array &$form): void {}

  /**
   * {@inheritdoc}
   */
  public function isEditable(ConfigEntityInterface $model): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isExportable(ConfigEntityInterface $model): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  final public function enable(ConfigEntityInterface $model): void {
    $data = $this->getModelData($model);
    if ($data !== '') {
      $owner = $this->api->findOwner($model);
      $modeler = $this->getModeler($model);
      $modeler->parseData($owner, $data);
      $modeler->enable($owner);
      $this->setModelData($model, $modeler->getRawData());
    }
    $this->setStatus($model, TRUE);
    $model->save();
  }

  /**
   * {@inheritdoc}
   */
  final public function disable(ConfigEntityInterface $model): void {
    $data = $this->getModelData($model);
    if ($data !== '') {
      $owner = $this->api->findOwner($model);
      $modeler = $this->getModeler($model);
      $modeler->parseData($owner, $data);
      $modeler->disable($owner);
      $this->setModelData($model, $modeler->getRawData());
    }
    $this->setStatus($model, FALSE);
    $model->save();
  }

  /**
   * {@inheritdoc}
   */
  final public function clone(ConfigEntityInterface $model, ?string $id = NULL, ?string $label = NULL): ConfigEntityInterface {
    $modeler = $this->getModeler($model);
    if ($id === NULL) {
      $id = $modeler->generateId();
    }
    if ($label === NULL) {
      $label = $this->getLabel($model) . ' (' . $this->t('clone') . ')';
    }
    $data = $this->getModelData($model);
    $newModel = NULL;
    if ($data !== '') {
      $owner = $this->api->findOwner($model);
      $modeler->parseData($owner, $data);
      $modeler->clone($owner, $id, $label);
      $newModel = $this->api->prepareModelFromData($modeler->getRawData(), $this->getPluginId(), $this->getModelerId($model), TRUE);
    }
    if ($newModel === NULL) {
      $entityType = $this->entityTypeManager->getDefinition($this->configEntityTypeId());
      $newModel = clone $model;
      $newModel->set($entityType->getKey('id'), $id);
      $newModel->setOriginalId($id);
      $newModel->enforceIsNew();
      if ($entityType->hasKey('uuid')) {
        $newModel->set($entityType->getKey('uuid'), $this->uuidGenerator->generate());
      }
      $this->setLabel($newModel, $label);
    }
    $newModel->save();
    return $newModel;
  }

  /**
   * {@inheritdoc}
   */
  final public function export(ConfigEntityInterface $model): Response {
    $filename = mb_strtolower($this->getPluginId()) . '-' . mb_strtolower($model->id()) . '.tar.gz';
    $tempFileName = 'temporary://' . $filename;
    $this->api->exportArchive($this, $model, $tempFileName);
    return new BinaryFileResponse($tempFileName, 200, [
      'Content-Type' => 'application/octet-stream',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  final public function setModelerId(ConfigEntityInterface $model, string $id): ModelOwnerInterface {
    return $this->setThirdPartySetting($model, 'modeler_id', $id);
  }

  /**
   * {@inheritdoc}
   */
  final public function getModelerId(ConfigEntityInterface $model): string {
    return $model->getThirdPartySetting('modeler_api', 'modeler_id', 'fallback');
  }

  /**
   * {@inheritdoc}
   */
  final public function getModeler(ConfigEntityInterface $model): ?ModelerInterface {
    try {
      $plugin = $this->modelerPluginManager->createInstance($this->getModelerId($model));
      if ($plugin instanceof ModelerInterface) {
        return $plugin;
      }
    }
    catch (PluginException) {
      // Ignore this exception.
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  final public function getUsedComponents(ConfigEntityInterface $model): array {
    $components = $this->usedComponents($model);
    $mapping = [];
    foreach ($this->getSwimlanes($model) as $id => $swimlane) {
      array_unshift($components, new Component(
        $this,
        $id,
        Api::COMPONENT_TYPE_SWIMLANE,
        '',
        $swimlane['name'],
      ));
      foreach ($swimlane['components'] as $componentId) {
        $mapping[$componentId] = $id;
      }
    }
    foreach ($components as $component) {
      if (isset($mapping[$component->getId()])) {
        $component->setParentId($mapping[$component->getId()]);
      }
    }
    return $components;
  }

  /**
   * {@inheritdoc}
   */
  final public function setLabel(ConfigEntityInterface $model, string $label): ModelOwnerInterface {
    if ($this->entityTypeManager->getDefinition($this->configEntityTypeId())->hasKey('label')) {
      $key = $this->entityTypeManager->getDefinition($this->configEntityTypeId())->getKey('label');
      $model->set($key, $label);
    }
    else {
      return $this->setThirdPartySetting($model, 'label', $label);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getLabel(ConfigEntityInterface $model): string {
    if ($this->entityTypeManager->getDefinition($this->configEntityTypeId())->hasKey('label')) {
      $key = $this->entityTypeManager->getDefinition($this->configEntityTypeId())->getKey('label');
      return $model->get($key) ?? '';
    }
    return $model->getThirdPartySetting('modeler_api', 'label', '');
  }

  /**
   * {@inheritdoc}
   */
  final public function setStatus(ConfigEntityInterface $model, bool $status): ModelOwnerInterface {
    if ($this->supportsStatus()) {
      $model->setStatus($status);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getStatus(ConfigEntityInterface $model): bool {
    return !$this->supportsStatus() || $model->status();
  }

  /**
   * {@inheritdoc}
   */
  final public function setVersion(ConfigEntityInterface $model, string $version): ModelOwnerInterface {
    return $this->setThirdPartySetting($model, 'version', $version);
  }

  /**
   * {@inheritdoc}
   */
  final public function getVersion(ConfigEntityInterface $model): string {
    return $model->getThirdPartySetting('modeler_api', 'version', '');
  }

  /**
   * {@inheritdoc}
   */
  final public function setTemplate(ConfigEntityInterface $model, bool $template): ModelOwnerInterface {
    if ($this->supportsTemplate()) {
      $model->set('template', $template);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getTemplate(ConfigEntityInterface $model): bool {
    $key = $this->entityTypeManager->getDefinition($this->configEntityTypeId())->getKey('template');
    return !$this->supportsTemplate() || $model->get($key);
  }

  /**
   * {@inheritdoc}
   */
  final public function setStorage(ConfigEntityInterface $model, string $storage): ModelOwnerInterface {
    return $this->setThirdPartySetting($model, 'storage', $storage);
  }

  /**
   * {@inheritdoc}
   */
  final public function getStorage(ConfigEntityInterface $model): string {
    return $model->getThirdPartySetting('modeler_api', 'storage', '');
  }

  /**
   * {@inheritdoc}
   */
  final public function setDocumentation(ConfigEntityInterface $model, string $documentation): ModelOwnerInterface {
    return $this->setThirdPartySetting($model, 'documentation', $documentation);
  }

  /**
   * {@inheritdoc}
   */
  final public function getDocumentation(ConfigEntityInterface $model): string {
    return $model->getThirdPartySetting('modeler_api', 'documentation', '');
  }

  /**
   * {@inheritdoc}
   */
  final public function setTags(ConfigEntityInterface $model, array $tags): ModelOwnerInterface {
    return $this->setThirdPartySetting($model, 'tags', $tags);
  }

  /**
   * {@inheritdoc}
   */
  final public function getTags(ConfigEntityInterface $model): array {
    return $model->getThirdPartySetting('modeler_api', 'tags', []);
  }

  /**
   * {@inheritdoc}
   */
  final public function setChangelog(ConfigEntityInterface $model, string $changelog): ModelOwnerInterface {
    return $this->setThirdPartySetting($model, 'changelog', $changelog);
  }

  /**
   * {@inheritdoc}
   */
  final public function getChangelog(ConfigEntityInterface $model): string {
    return $model->getThirdPartySetting('modeler_api', 'changelog', '');
  }

  /**
   * {@inheritdoc}
   */
  final public function setAnnotations(ConfigEntityInterface $model, array $annotations): ModelOwnerInterface {
    $items = [];
    foreach ($annotations as $annotation) {
      $associations = [];
      foreach ($annotation->getSuccessors() as $association) {
        $associations[$association->getId()] = $association->getConditionId();
      }
      $items[$annotation->getId()] = [
        'text' => $annotation->getLabel(),
        'assigned_to' => $associations,
      ];
    }
    return $this->setThirdPartySetting($model, 'annotations', $items);
  }

  /**
   * {@inheritdoc}
   */
  final public function getAnnotations(ConfigEntityInterface $model): array {
    $annotations = [];
    foreach ($model->getThirdPartySetting('modeler_api', 'annotations', []) as $id => $annotation) {
      $associations = [];
      foreach ($annotation['assigned_to'] as $associationId => $sourceId) {
        $associations[] = new ComponentSuccessor($associationId, $sourceId);
      }
      $annotations[] = new Component(
        $this,
        $id,
        Api::COMPONENT_TYPE_ANNOTATION,
        '',
        $annotation['text'],
        [],
        $associations,
      );
    }
    return $annotations;
  }

  /**
   * {@inheritdoc}
   */
  final public function setColors(ConfigEntityInterface $model, array $colors): ModelOwnerInterface {
    $items = [];
    foreach ($colors as $id => $color) {
      $items[$id] = [
        'fill' => $color->getFill(),
        'stroke' => $color->getStroke(),
      ];
    }
    return $this->setThirdPartySetting($model, 'colors', $items);
  }

  /**
   * {@inheritdoc}
   */
  final public function getColors(ConfigEntityInterface $model): array {
    $colors = [];
    foreach ($model->getThirdPartySetting('modeler_api', 'colors', []) as $id => $color) {
      $colors[$id] = new ComponentColor($color['fill'], $color['stroke']);
    }
    return $colors;
  }

  /**
   * {@inheritdoc}
   */
  final public function setSwimlanes(ConfigEntityInterface $model, array $swimlanes): ModelOwnerInterface {
    $items = [];
    foreach ($swimlanes as $swimlane) {
      if ($swimlane['id'] === NULL) {
        continue;
      }
      $items[$swimlane['id']] = [
        'name' => $swimlane['name'],
        'components' => $swimlane['components'],
      ];
    }
    return $this->setThirdPartySetting($model, 'swimlanes', $items);
  }

  /**
   * {@inheritdoc}
   */
  final public function getSwimlanes(ConfigEntityInterface $model): array {
    return $model->getThirdPartySetting('modeler_api', 'swimlanes', []);
  }

  /**
   * {@inheritdoc}
   */
  final public function setModelData(ConfigEntityInterface $model, string $data): ModelOwnerInterface {
    $deleteSeparate = $deleteEmbedded = FALSE;
    switch ($this->storageMethod($model)) {
      case Settings::STORAGE_OPTION_SEPARATE:
        $hash = 'hash:' . hash('md5', $data);
        $this->setThirdPartySetting($model, 'data', $hash);
        $this->dataModelEntity($model)
          ->set('data', $data)
          ->save();
        break;

      case Settings::STORAGE_OPTION_THIRD_PARTY:
        $this->setThirdPartySetting($model, 'data', $data);
        $deleteSeparate = TRUE;
        break;

      default:
        $deleteSeparate = $deleteEmbedded = TRUE;
        break;
    }
    if ($deleteSeparate) {
      $this->dataModelEntity($model)->delete();
    }
    if ($deleteEmbedded) {
      $model->unsetThirdPartySetting('modeler_api', 'data');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getModelData(ConfigEntityInterface $model): string {
    $data = $model->getThirdPartySetting('modeler_api', 'data', '');
    if ($data !== '') {
      if (is_string($data) && strlen($data) === 37 && str_starts_with($data, 'hash:')) {
        $hash = substr($data, 5);
        $dataModel = $this->dataModelEntity($model);
        $data = $dataModel->get('data');
        hash_equals($hash, hash('md5', $data)) ?: $data = '';
        if ($data === '') {
          $dataModel->delete();
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSchemaKey(PluginInspectionInterface $plugin): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function skipConfigurationValidation(int $type, string $id): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function docBaseUrl(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function pluginDocUrl(PluginInspectionInterface $plugin, string $pluginType): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  final public function storageMethod(ConfigEntityInterface $model): ?string {
    $modeler = $this->getModeler($model);
    if ($modeler->getPluginId() === 'fallback') {
      return NULL;
    }
    $method = $this->getStorage($model);
    if ($method === '') {
      $method = Settings::value($this, $modeler, 'storage', $this->defaultStorageMethod());
    }
    if ($model->isNew() && $method === Settings::STORAGE_OPTION_SEPARATE) {
      // A new model has no ID, so we can't store externally.
      // Keep it in third-party until the entity got saved the first time.
      $method = Settings::STORAGE_OPTION_THIRD_PARTY;
    }
    return $method;
  }

  /**
   * {@inheritdoc}
   */
  final public function storageId(ConfigEntityInterface $model): string {
    // This needs to be the same as in the settings submit.
    // @see \Drupal\modeler_api\Form\Settings::submitForm
    return implode('_', [
      $model->getEntityTypeId(),
      $this->getModelerId($model),
      $model->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormFieldForValidation(?string &$value, ?string &$replacement, array $element): ?string {
    return NULL;
  }

  /**
   * Get the data model config entity matching the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Entity\DataModel
   *   The data model config entity.
   */
  protected function dataModelEntity(ConfigEntityInterface $model): DataModel {
    $storage = $this->entityTypeManager->getStorage('modeler_api_data_model');
    $id = $this->storageId($model);
    /** @var \Drupal\modeler_api\Entity\DataModel|null $dataModel */
    $dataModel = $storage->load($id);
    if (!$dataModel) {
      /** @var \Drupal\modeler_api\Entity\DataModel|null $dataModel */
      $dataModel = $storage->create([
        'id' => $id,
        'data' => '',
      ]);
    }
    return $dataModel;
  }

  /**
   * Set or remove a third-party settings.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  protected function setThirdPartySetting(ConfigEntityInterface $model, string $key, mixed $value): ModelOwnerInterface {
    if (empty($value)) {
      $model->unsetThirdPartySetting('modeler_api', $key);
    }
    else {
      $model->setThirdPartySetting('modeler_api', $key, $value);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeAddingComponents(ConfigEntityInterface $model): void {}

  /**
   * {@inheritdoc}
   */
  public function defaultStorageMethod(): string {
    return Settings::STORAGE_OPTION_THIRD_PARTY;
  }

  /**
   * {@inheritdoc}
   */
  public function enforceDefaultStorageMethod(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  final public function supportsStatus(): bool {
    return $this->entityTypeManager->getDefinition($this->configEntityTypeId())->hasKey('status');
  }

  /**
   * {@inheritdoc}
   */
  final public function supportsTemplate(): bool {
    return $this->entityTypeManager->getDefinition($this->configEntityTypeId())->hasKey('template');
  }

  /**
   * {@inheritdoc}
   */
  public function applyTemplate(string $templateId, string $componentId, string $target, array $hiddenConfig = [], array $config = []): void {}

  /**
   * {@inheritdoc}
   */
  public function ownerComponentEditable(PluginInspectionInterface $plugin): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentPluginChangeable(PluginInspectionInterface $plugin): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function favoriteOwnerComponents(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsReplayData(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReplayData(string $hash): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getReplayDataByComponent(string $modelId, string $componentId): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsTesting(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function startTestJob(string $modelId, string $componentId): string|TranslatableMarkup {
    return $this->t('Testing is not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function pollTestJob(string $jobId): array|null|TranslatableMarkup {
    return $this->t('Testing is not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function cancelTestJob(string $jobId): null|TranslatableMarkup {
    return $this->t('Testing is not supported.');
  }

}
