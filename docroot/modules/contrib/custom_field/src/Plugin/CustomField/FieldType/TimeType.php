<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Time;

/**
 * Plugin implementation of the 'time' custom field type.
 */
#[CustomFieldType(
  id: 'time',
  label: new TranslatableMarkup('Time'),
  description: new TranslatableMarkup('A field containing a Time.'),
  category: new TranslatableMarkup('Date/Time'),
  default_widget: 'time_widget',
  default_formatter: 'time',
  constraints: [
    'CustomFieldTime' => [],
  ]
)]
class TimeType extends CustomFieldTypeBase {

  use TypedDataTrait;

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    return [
      $settings['name'] => [
        'type' => 'int',
        'unsigned' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {

    $properties = [];
    $properties[$settings['name']] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $settings['name']]))
      ->setDescription(new TranslatableMarkup('Seconds passed through midnight'))
      ->setSetting('unsigned', TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'seconds_enabled' => FALSE,
      'seconds_step' => 5,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $field_name = $this->getName();

    $element['seconds_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add seconds parameter to input widget'),
      '#default_value' => $settings['seconds_enabled'],
    ];
    $element['seconds_step'] = [
      '#type' => 'number',
      '#title' => $this->t('Step to change seconds'),
      '#open' => TRUE,
      '#default_value' => $settings['seconds_step'],
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $field_name . '][seconds_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    /** @var array<string, mixed> $definition */
    $definition = $this->pluginDefinition;
    return $definition['constraints'];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): string|array {
    // Generate random hours (1–12), minutes (0–59).
    $hours = rand(0, 23);
    $minutes = rand(0, 59);

    // Format with leading zeros.
    $time = sprintf('%02d:%02d:00', $hours, $minutes);

    return (string) Time::createFromHtml5Format($time)->getTimestamp();
  }

}
