<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'map' field type.
 */
#[CustomFieldType(
  id: 'map',
  label: new TranslatableMarkup('Serialized - Key/Value'),
  description: new TranslatableMarkup('A field for storing a serialized array of values.'),
  category: new TranslatableMarkup('Map'),
  default_widget: 'map_key_value',
  default_formatter: 'string',
)]
class MapType extends CustomFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
      'description' => 'A serialized array of values.',
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = MapDataDefinition::create()
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'table_empty' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();

    $element['table_empty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty text'),
      '#description' => $this->t('The text to display when no items have been added.'),
      '#default_value' => $settings['table_empty'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): array {
    $random = new Random();
    $map_values = [];
    for ($i = 0; $i < 5; $i++) {
      $map_values[] = [
        'key' => $random->word(10),
        'value' => $random->word(mt_rand(10, 20)),
      ];
    }

    return $map_values;
  }

}
