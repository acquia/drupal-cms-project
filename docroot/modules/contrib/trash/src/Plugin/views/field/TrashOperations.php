<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\views\field;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityOperations;

/**
 * Renders operations links for trashed entities without a list builder.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("trash_operations")]
class TrashOperations extends EntityOperations {

  /**
   * {@inheritdoc}
   */
  public function render($values) {
    $entity = $this->getEntity($values);
    if (empty($entity)) {
      return '';
    }

    $entity = $this->getEntityTranslationByRelationship($entity, $values);
    /** @var \Drupal\Core\Entity\EntityListBuilder $list_builder */
    $list_builder = $this->entityTypeManager->createHandlerInstance(EntityListBuilder::class, $entity->getEntityType());
    $operations = $list_builder->getOperations($entity);

    if ($this->options['destination']) {
      foreach ($operations as &$operation) {
        if (!isset($operation['query'])) {
          $operation['query'] = [];
        }
        $operation['query'] += $this->getDestinationArray();
      }
    }

    return [
      '#type' => 'operations',
      '#links' => $operations,
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];
  }

}
