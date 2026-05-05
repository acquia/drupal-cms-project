<?php

declare(strict_types=1);

namespace Drupal\custom_field\Trait;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Trait for field formatter plugins.
 */
trait FieldFormatterTrait {

  /**
   * Helper function to prepare the formatted value for a subfield.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The custom field item.
   * @param string $name
   *   The name of the subfield.
   * @param string $langcode
   *   The language code.
   *
   * @return mixed
   *   The prepared value to pass to formatter plugins.
   */
  protected static function prepareFormattedSubfieldValue(FieldItemInterface $item, CustomFieldTypeInterface $custom_item, string $name, string $langcode): mixed {
    $data_type = $custom_item->getDataType();
    $value = $custom_item->value($item);
    if ($value === '' || $value === NULL) {
      return NULL;
    }

    switch ($data_type) {
      case 'viewfield':
        $value = [
          'target_id' => $value,
          'display_id' => $item->{$name . '__display'},
          'arguments' => $item->{$name . '__arguments'},
          'items_to_display' => $item->{$name . '__items'},
        ];
        break;

      case 'uri':
        $value = [
          'uri' => $value,
        ];
        break;

      case 'link':
        $value = [
          'uri' => $value,
          'title' => $item->{$name . '__title'},
          'options' => $item->{$name . '__options'},
        ];
        break;

      case 'datetime':
        $value = [
          'date' => $item->{$name . '__date'},
          'timezone' => $item->{$name . '__timezone'},
        ];
        break;

      case 'daterange':
        $value = [
          'start_date' => $item->{$name . '__start_date'},
          'end_date' => $item->{$name . '__end_date'},
          'timezone' => $item->{$name . '__timezone'},
          'duration' => $item->{$name . '__duration'},
        ];
        break;

      case 'time_range':
        $value = [
          'start' => $value,
          'end' => $item->{$name . '__end'},
          'duration' => $item->{$name . '__duration'},
        ];
        break;

      case 'entity_reference':
      case 'file':
      case 'image':
        $entity = $item->{$name . '__entity'};
        if (!$entity instanceof EntityInterface) {
          return NULL;
        }
        if ($entity instanceof TranslatableInterface) {
          /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
          $entity_repository = \Drupal::service('entity.repository');
          $entity = $entity_repository->getTranslationFromContext($entity, $langcode);
        }
        $value = $entity;
        break;
    }

    return $value;
  }

  /**
   * Helper function to return default wrapper settings.
   *
   * @return string[]
   *   An array of default wrapper settings.
   */
  protected static function defaultWrappers(): array {
    return [
      'field_wrapper_tag' => '',
      'field_wrapper_classes' => '',
      'field_tag' => '',
      'field_classes' => '',
      'label_tag' => '',
      'label_classes' => '',
    ];
  }

}
