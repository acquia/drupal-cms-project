<?php

namespace Drupal\eca_content\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Implements content hooks for the ECA Content submodule.
 */
class ContentHooks {

  /**
   * The ECA settings.
   *
   * Lazy-loaded to avoid circular dependencies during hook discovery.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|null
   */
  protected ?ImmutableConfig $ecaSettings = NULL;

  /**
   * Constructs a new ContentHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected ContentEntityTypes $entityTypes,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    // Config is lazy-loaded to avoid circular dependencies during hook
    // discovery.
  }

  /**
   * Gets ECA settings, lazy-loading to avoid circular dependencies.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The ECA settings config.
   */
  protected function getEcaSettings(): ImmutableConfig {
    if ($this->ecaSettings === NULL) {
      $this->ecaSettings = $this->configFactory->get('eca.settings');
    }
    return $this->ecaSettings;
  }

  /**
   * Implements hook_entity_bundle_create().
   */
  #[Hook('entity_bundle_create')]
  public function entityBundleCreate(string $entity_type_id, string $bundle): void {
    $this->triggerEvent->dispatchFromPlugin('content_entity:bundlecreate', $entity_type_id, $bundle, $this->entityTypes);
  }

  /**
   * Implements hook_entity_bundle_delete().
   */
  #[Hook('entity_bundle_delete')]
  public function entityBundleDelete(string $entity_type_id, string $bundle): void {
    $this->triggerEvent->dispatchFromPlugin('content_entity:bundledelete', $entity_type_id, $bundle, $this->entityTypes);
  }

  /**
   * Implements hook_entity_create().
   */
  #[Hook('entity_create')]
  public function entityCreate(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:create', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_revision_create().
   */
  #[Hook('entity_revision_create')]
  public function entityRevisionCreate(EntityInterface $new_revision, EntityInterface $entity, ?bool $keep_untranslatable_fields): void {
    if ($new_revision instanceof ContentEntityInterface && $entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:revisioncreate', $new_revision, $this->entityTypes, $entity, $keep_untranslatable_fields);
    }
  }

  /**
   * Implements hook_entity_preload().
   */
  #[Hook('entity_preload')]
  public function entityPreload(array $ids, string $entity_type_id): void {
    $this->triggerEvent->dispatchFromPlugin('content_entity:preload', $ids, $entity_type_id);
  }

  /**
   * Implements hook_entity_load().
   */
  #[Hook('entity_load')]
  public function entityLoad(array $entities, string $entity_type_id): void {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $this->triggerEvent->dispatchFromPlugin('content_entity:load', $entity, $this->entityTypes);
      }
    }
  }

  /**
   * Implements hook_entity_storage_load().
   */
  #[Hook('entity_storage_load')]
  public function entityStorageLoad(array $entities, string $entity_type): void {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $this->triggerEvent->dispatchFromPlugin('content_entity:storageload', $entity, $this->entityTypes);
      }
    }
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:presave', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:insert', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      if ($entity->getEntityType()->hasKey('revision')) {
        // Make sure the subsequent actions will not create another revision
        // when they save this entity again.
        $entity->setNewRevision(FALSE);
        $entity->updateLoadedRevisionId();
      }
      $this->triggerEvent->dispatchFromPlugin('content_entity:update', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_translation_create().
   */
  #[Hook('entity_translation_create')]
  public function entityTranslationCreate(EntityInterface $translation): void {
    if ($translation instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:translationcreate', $translation, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_translation_insert().
   */
  #[Hook('entity_translation_insert')]
  public function entityTranslationInsert(EntityInterface $translation): void {
    if ($translation instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:translationinsert', $translation, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_translation_delete().
   */
  #[Hook('entity_translation_delete')]
  public function entityTranslationDelete(EntityInterface $translation): void {
    if ($translation instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:translationdelete', $translation, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:predelete', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:delete', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_revision_delete().
   */
  #[Hook('entity_revision_delete')]
  public function entityRevisionDelete(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:revisiondelete', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:view', $entity, $this->entityTypes, $build, $display, $view_mode);
    }
  }

  /**
   * Implements hook_entity_prepare_view().
   */
  #[Hook('entity_prepare_view')]
  public function entityPrepareView(string $entity_type_id, array $entities, array $displays, string $view_mode): void {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $this->triggerEvent->dispatchFromPlugin('content_entity:prepareview', $entity, $this->entityTypes, $displays, $view_mode);
      }
    }
  }

  /**
   * Implements hook_entity_prepare_form().
   */
  #[Hook('entity_prepare_form')]
  public function entityPrepareForm(EntityInterface &$entity, ?string $operation, FormStateInterface $form_state): void {
    if ($entity instanceof ContentEntityInterface) {
      /** @var \Drupal\eca_content\Event\ContentEntityPrepareForm $event */
      $event = $this->triggerEvent->dispatchFromPlugin('content_entity:prepareform', $entity, $this->entityTypes, $operation, $form_state);
      $entity = $event->getEntity();
    }
  }

  /**
   * Implements hook_entity_field_values_init().
   */
  #[Hook('entity_field_values_init')]
  public function entityFieldValuesInit(FieldableEntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:fieldvaluesinit', $entity, $this->entityTypes);
    }
  }

  /**
   * Implements hook_entity_view_mode_alter().
   */
  #[Hook('entity_view_mode_alter')]
  public function entityViewModeAlter(string &$view_mode, EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      /** @var \Drupal\eca_content\Event\ContentEntityViewModeAlter $event */
      $event = $this->triggerEvent->dispatchFromPlugin('content_entity:viewmodealter', $entity, $this->entityTypes, $view_mode);
      $view_mode = $event->getViewMode();
    }
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    foreach ($entity_types as $entity_type) {
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        // Add ECA's chameleon validation constraint to the content entity.
        $entity_type->addConstraint('EcaContent');
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for field_config entities.
   *
   * Updates existing ECA configuration dependencies that already refer to field
   * configurations having the same field name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  #[Hook('field_config_insert')]
  public function fieldConfigInsert(EntityInterface $entity): void {
    if (!in_array('new_field_config', $this->getEcaSettings()->get('dependency_calculation') ?? [], TRUE)) {
      // Nothing to do.
      return;
    }

    $eca_configs = $this->entityTypeManager->getStorage('eca')->loadMultiple();
    if (empty($eca_configs)) {
      // No ECA config present, thus nothing to do here.
      return;
    }

    // List of updated ECA configs that need to be saved.
    $to_save = [];

    /**
     * @var \Drupal\field\FieldConfigInterface $field_config
     */
    $field_config = $entity;
    $field_name = $field_config->getName();
    $entity_type_id = $field_config->getTargetEntityTypeId();
    $field_storage_config_id = "field.storage.$entity_type_id.$field_name";
    $field_field_config_id = 'field.field.' . $field_config->id();

    /**
     * @var \Drupal\eca\Entity\Eca $eca
     */
    foreach ($eca_configs as $eca) {
      $eca_dependencies = $eca->getDependencies();
      foreach ($eca_dependencies as $type => $dependencies) {
        if (!in_array($type, ['config', 'module'])) {
          continue;
        }
        $uses_field = in_array($field_storage_config_id, $dependencies, TRUE);
        if ($uses_field && !in_array($field_field_config_id, $dependencies, TRUE)) {
          $eca->addRuntimeDependency($field_config->getConfigDependencyKey(), $field_field_config_id);
          $to_save[$eca->id()] = $eca;
        }
      }
    }

    /**
     * @var \Drupal\eca\Entity\Eca $eca
     */
    foreach ($to_save as $eca) {
      $eca->save();
    }
  }

}
