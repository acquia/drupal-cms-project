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

  /**
   * Helper function to build HTML wrappers for a subfield.
   *
   * @param array<string, mixed> $element
   *   The form element to modify.
   * @param string $visibility_path
   *   The states api visibility path string.
   * @param array<string, mixed> $settings
   *   The wrapper settings.
   * @param array<string, mixed> $tag_options
   *   The HTML element options.
   * @param string $plugin_id
   *   The sub-field plugin ID.
   *
   * @return array
   *   The modified form element.
   */
  protected function buildHtmlWrappers(array &$element, string $visibility_path, $settings, $tag_options, string $plugin_id): array {
    $element['wrappers'] = [
      '#type' => 'details',
      '#title' => $this->t('Style settings'),
      '#states' => [
        'visible' => [
          ':input[name="' . $visibility_path . '[format_type]"]' => ['!value' => 'hidden'],
        ],
      ],
    ];
    $element['wrappers']['field_wrapper_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Field wrapper tag'),
      '#description' => $this->t('Choose the HTML element to wrap around this field and label.'),
      '#options' => $tag_options,
      '#empty_option' => $this->t('- Use default -'),
      '#default_value' => $settings['field_wrapper_tag'] ?? '',
    ];
    $element['wrappers']['field_wrapper_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field wrapper classes'),
      '#description' => $this->t('Enter additional classes, separated by space.'),
      '#default_value' => $settings['field_wrapper_classes'] ?? '',
      '#states' => [
        'invisible' => [
          ':input[name="' . $visibility_path . '[wrappers][field_wrapper_tag]"]' => ['value' => 'none'],
        ],
      ],
    ];
    $element['wrappers']['field_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Field tag'),
      '#description' => $this->t('Choose the HTML element to wrap around this field.'),
      '#options' => $tag_options,
      '#empty_option' => $this->t('- Use default -'),
      '#default_value' => $settings['field_tag'] ?? '',
    ];
    $element['wrappers']['field_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field classes'),
      '#description' => $this->t('Enter additional classes, separated by space.'),
      '#default_value' => $settings['field_classes'] ?? '',
      '#states' => [
        'invisible' => [
          ':input[name="' . $visibility_path . '[wrappers][field_tag]"]' => ['value' => 'none'],
        ],
      ],
    ];
    $element['wrappers']['label_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Label tag'),
      '#description' => $this->t('Choose the HTML element to wrap around this label.'),
      '#options' => $tag_options,
      '#empty_option' => $this->t('- Use default -'),
      '#default_value' => $settings['label_tag'] ?? '',
      '#access' => !($plugin_id === 'boolean'),
      '#states' => [
        'visible' => [
          ':input[name="' . $visibility_path . '[formatter_settings][label_display]"]' => ['!value' => 'hidden'],
        ],
      ],
    ];

    $element['wrappers']['label_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label classes'),
      '#description' => $this->t('Enter additional classes, separated by space.'),
      '#default_value' => $settings['label_classes'] ?? '',
      '#access' => !($plugin_id === 'boolean'),
      '#states' => [
        'visible' => [
          ':input[name="' . $visibility_path . '[formatter_settings][label_display]"]' => ['!value' => 'hidden'],
          ':input[name="' . $visibility_path . '[wrappers][label_tag]"]' => ['!value' => 'none'],
        ],
      ],
    ];

    return $element;
  }

}
