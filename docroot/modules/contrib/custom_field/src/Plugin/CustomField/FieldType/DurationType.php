<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'integer' field type.
 */
#[CustomFieldType(
  id: 'duration',
  label: new TranslatableMarkup('Duration'),
  description: new TranslatableMarkup('This field stores a calculated number of seconds as an integer.'),
  category: new TranslatableMarkup('Date/Time'),
  default_widget: 'duration',
  default_formatter: 'duration',
)]
class DurationType extends CustomFieldTypeBase {

  use DurationOptionsTrait;

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'description' => 'The number of seconds.',
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'duration_options' => [
        ['key' => 86400, 'label' => new TranslatableMarkup('1 day')],
        ['key' => 604800, 'label' => new TranslatableMarkup('1 week')],
        ['key' => 2592000, 'label' => new TranslatableMarkup('1 month')],
      ],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $this->durationOptions($element, $form_state, $settings);
    $element['duration_options']['#element_validate'][] = [static::class, 'validateDurationOptions'];

    return $element;
  }

  /**
   * Validates the duration options field is not empty.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateDurationOptions(array &$element, FormStateInterface $form_state): void {
    $duration_options = $element['#allowed_values'];
    if (empty($duration_options)) {
      $form_state->setError($element, new TranslatableMarkup('The duration options field cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): int {
    $field_settings = $field->getFieldSettings();
    $duration_options = $field_settings['duration_options'] ?? [];
    return (int) static::getRandomOptions($duration_options);
  }

}
