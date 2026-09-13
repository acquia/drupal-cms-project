<?php

namespace Drupal\eca_queue\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\eca\Event\TriggerEvent;

/**
 * Implements queue hooks for the ECA Queue submodule.
 */
class QueueHooks {

  /**
   * Constructs a new QueueHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected QueueWorkerManagerInterface $queueWorkerManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for eca entities.
   */
  #[Hook('eca_insert')]
  public function ecaInsert(EntityInterface $entity): void {
    if ($this->queueWorkerManager instanceof DefaultPluginManager) {
      $this->queueWorkerManager->clearCachedDefinitions();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for eca entities.
   */
  #[Hook('eca_update')]
  public function ecaUpdate(EntityInterface $entity): void {
    $this->ecaInsert($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for eca entities.
   */
  #[Hook('eca_delete')]
  public function ecaDelete(EntityInterface $entity): void {
    $this->ecaInsert($entity);
  }

}
