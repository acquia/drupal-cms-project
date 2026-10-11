<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Action\Derivative;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityActionDeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides restore action definitions for all trash-enabled entity types.
 */
class TrashRestoreActionDeriver extends EntityActionDeriverBase {

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = parent::create($container, $base_plugin_id);
    $instance->trashManager = $container->get('trash.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      foreach ($this->getApplicableEntityTypes() as $entity_type_id => $entity_type) {
        $this->derivatives[$entity_type_id] = [
          'type' => $entity_type_id,
          'label' => $this->t('Restore @entity_type from trash', [
            '@entity_type' => $entity_type->getSingularLabel(),
          ]),
          'confirm_form_route_name' => "entity.$entity_type_id.restore_multiple",
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type): bool {
    return $this->trashManager->isEntityTypeEnabled($entity_type->id());
  }

}
