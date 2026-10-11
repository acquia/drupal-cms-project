<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * Plugin implementation of the 'string_long' field type.
 */
#[CustomFieldType(
  id: 'string_long',
  label: new TranslatableMarkup('Text (long)'),
  description: new TranslatableMarkup('A field containing a long string value.'),
  category: new TranslatableMarkup('Text'),
  default_widget: 'textarea',
  default_formatter: 'text_default',
)]
class StringLongType extends CustomFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'text',
      'size' => 'big',
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_string_long')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'formatted' => FALSE,
      'default_format' => '',
      'format' => [
        'guidelines' => TRUE,
        'help' => TRUE,
      ],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $name = $this->getName();
    $formats = filter_formats();
    $format_options = array_map(function ($format) {
      return $format->get('name');
    }, $formats);

    $element['formatted'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable wysiwyg'),
      '#default_value' => $settings['formatted'],
    ];
    $element['default_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Default format'),
      '#options' => $format_options,
      '#default_value' => $settings['default_format'],
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $name . '][formatted]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['format'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Format settings'),
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $name . '][formatted]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['format']['guidelines'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show format guidelines'),
      '#default_value' => $settings['format']['guidelines'],
    ];
    $element['format']['help'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show format help'),
      '#default_value' => $settings['format']['help'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): string {
    $random = new Random();
    return $random->paragraphs();
  }

}
