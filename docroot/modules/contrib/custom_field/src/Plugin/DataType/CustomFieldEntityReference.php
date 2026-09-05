<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * Defines the "custom_field_entity_reference" data type.
 *
 * The "custom_field_entity_reference" data type provides a way to process
 * entity as part of values.
 */
#[DataType(
  id: 'custom_field_entity_reference',
  label: new TranslatableMarkup('Entity reference'),
  definition_class: CustomFieldDataDefinition::class,
)]
class CustomFieldEntityReference extends CustomFieldEntityReferenceBase {

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    $entity = $value['entity'] ?? NULL;
    // Drupal core Default Content API Importer will call this method with an
    // array of exported entity reference data. If this is the case, try loading
    // the referenced entity here, before assigning as data value.
    if (!$entity && is_string($value) && Uuid::isValid($value)) {
      $entity = $this->getEntityByUuid($value);
    }

    if ($entity instanceof EntityInterface) {
      if ($entity->isNew()) {
        try {
          $entity->save();
        }
        catch (EntityStorageException $exception) {
          $entity = NULL;
        }
      }
      $this->entity = $entity;
      $value = $entity?->id();
    }
    $this->value = $value['target_id'] ?? $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    $data_type = $this->getDataDefinition()->getSetting('data_type');
    $value = $this->getValue();
    if ($data_type === 'integer') {
      return (int) $value;
    }
    return (string) $value;
  }

}
