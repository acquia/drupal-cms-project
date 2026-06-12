<?php

declare(strict_types=1);

namespace Drupal\trash\EventSubscriber;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\trash\TrashManagerInterface;
use Drupal\views\ViewsData;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to the config save event for trash.settings.
 */
class TrashConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository,
    protected RouteBuilderInterface $routeBuilder,
    protected DrupalKernelInterface $kernel,
    #[Autowire(service: 'plugin.manager.action')]
    protected ?ActionManager $actionManager = NULL,
    protected ?ViewsData $viewsData = NULL,
  ) {}

  /**
   * Enables or disables trash integration for entity types.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The ConfigCrudEvent to process.
   */
  public function onSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'trash.settings') {
      $supported_entity_types = array_filter($this->entityTypeManager->getDefinitions(), function ($entity_type) {
        return $this->trashManager->isEntityTypeSupported($entity_type);
      });
      $enabled_entity_types = $event->getConfig()->get('enabled_entity_types');

      // Work around core bug #2605144, which doesn't provide the original
      // config data on import, only on regular save.
      // @see https://www.drupal.org/project/drupal/issues/2605144
      foreach ($supported_entity_types as $entity_type_id => $entity_type) {
        $field_storage_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);

        // Enable trash integration for the requested entity types.
        if (isset($enabled_entity_types[$entity_type_id]) && !isset($field_storage_definitions['deleted'])) {
          $this->trashManager->enableEntityType($entity_type);
          $this->createTrashActions($entity_type_id);
        }

        // Disable trash integration for the rest of the entity types.
        if (!isset($enabled_entity_types[$entity_type_id])
            && isset($field_storage_definitions['deleted'])
            && $field_storage_definitions['deleted']->getProvider() === 'trash') {
          $this->trashManager->disableEntityType($entity_type);
          $this->deleteTrashActions($entity_type_id);
        }
      }

      // When an entity type is enabled or disabled, the router needs to be
      // rebuilt to add the corresponding tabs in the trash UI.
      $this->routeBuilder->setRebuildNeeded();

      // The container also needs to be rebuilt in order to update the trash
      // handler services.
      // @see \Drupal\trash\Handler\TrashHandlerPass::process()
      $this->kernel->invalidateContainer();

      // The views data need to be cleared to register the new 'deleted' field.
      $this->viewsData?->clear();
    }
  }

  /**
   * Creates action config entities for trash operations on an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   */
  protected function createTrashActions(string $entity_type_id): void {
    // Clear the action plugin manager cache so that the new derivatives
    // for this entity type are discovered. The derivers check if an entity
    // type is trash-enabled, and the config was just saved so the cache is
    // stale.
    $this->actionManager?->clearCachedDefinitions();

    $action_storage = $this->entityTypeManager->getStorage('action');
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $singular_label = $entity_type->getSingularLabel();

    // Create restore action.
    $restore_action_id = "{$entity_type_id}_restore_action";
    if (!$action_storage->load($restore_action_id)) {
      $action_storage->create([
        'id' => $restore_action_id,
        'label' => "Restore $singular_label from trash",
        'type' => $entity_type_id,
        'plugin' => "entity:restore_action:{$entity_type_id}",
        'configuration' => [],
      ])->save();
    }

    // Create purge action.
    $purge_action_id = "{$entity_type_id}_purge_action";
    if (!$action_storage->load($purge_action_id)) {
      $action_storage->create([
        'id' => $purge_action_id,
        'label' => "Permanently delete $singular_label",
        'type' => $entity_type_id,
        'plugin' => "entity:purge_action:{$entity_type_id}",
        'configuration' => [],
      ])->save();
    }
  }

  /**
   * Deletes action config entities for trash operations on an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   */
  protected function deleteTrashActions(string $entity_type_id): void {
    $action_storage = $this->entityTypeManager->getStorage('action');
    $actions = $action_storage->loadMultiple([
      "{$entity_type_id}_restore_action",
      "{$entity_type_id}_purge_action",
    ]);
    $action_storage->delete($actions);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
