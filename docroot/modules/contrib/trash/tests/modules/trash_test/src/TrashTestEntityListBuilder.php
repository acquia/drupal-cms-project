<?php

namespace Drupal\trash_test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of trash test entities.
 *
 * @see \Drupal\trash_test\Entity\TrashTestEntity
 */
class TrashTestEntityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Trash test');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->toLink();
    return $row + parent::buildRow($entity);
  }

}
