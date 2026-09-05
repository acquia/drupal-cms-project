<?php

namespace Drupal\eca\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\PluginManager\Action;

/**
 * Implements action hooks for the ECA module.
 */
class ActionHooks {

  /**
   * Constructs a new ActionHooks object.
   *
   * @param \Drupal\eca\PluginManager\Action $actionPluginManager
   *   The ECA action plugin manager.
   */
  public function __construct(
    protected Action $actionPluginManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for action entities.
   */
  #[Hook('action_insert')]
  public function actionInsert(EntityInterface $entity): void {
    $this->actionPluginManager->clearCachedDefinitions();
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for action entities.
   */
  #[Hook('action_update')]
  public function actionUpdate(EntityInterface $entity): void {
    $this->actionPluginManager->clearCachedDefinitions();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for action entities.
   */
  #[Hook('action_delete')]
  public function actionDelete(EntityInterface $entity): void {
    $this->actionPluginManager->clearCachedDefinitions();
  }

}
