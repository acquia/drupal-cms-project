<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\Entity\EntityInterface;

/**
 * Base class for custom_field reference DataType plugins.
 */
abstract class CustomFieldEntityReferenceBase extends CustomFieldDataTypeBase implements CustomFieldEntityReferenceInterface {

  /**
   * The entity object or null.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ?EntityInterface {
    if (empty($this->entity) && !empty($this->value)) {
      $target_type = $this->getDataDefinition()->getSetting('target_type');
      $storage = \Drupal::entityTypeManager()->getStorage($target_type);
      $this->entity = $storage->load($this->getValue());
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityByUuid(string $uuid): ?EntityInterface {
    $target_type = $this->getDataDefinition()->getSetting('target_type');
    $storage = \Drupal::entityTypeManager()->getStorage($target_type);
    $entities = $storage->loadByProperties(['uuid' => $uuid]);
    return !empty($entities) ? current($entities) : NULL;
  }

}
