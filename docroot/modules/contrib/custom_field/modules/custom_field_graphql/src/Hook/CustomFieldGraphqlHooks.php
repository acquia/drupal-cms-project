<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use function Symfony\Component\String\u;

/**
 * Provides hooks related to config schemas.
 */
class CustomFieldGraphqlHooks {

  use StringTranslationTrait;

  /**
   * Creates a CustomFieldGraphqlHooks object.
   */
  public function __construct(protected CustomFieldTypeManagerInterface $customFieldTypeManager) {}

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    $definitions['graphql_compose.field.*.*.*']['mapping']['subfields'] = [
      'type' => 'sequence',
      'label' => $this->t('Subfield settings'),
      'sequence' => [
        'type' => 'custom_field_graphql.subfield.[%key]',
      ],
    ];
  }

  /**
   * Implements hook_graphql_compose_field_type_form_alter().
   */
  #[Hook('graphql_compose_field_type_form_alter')]
  public function graphqlComposeFieldTypeFormAlter(array &$form, FormStateInterface $form_state, FieldDefinitionInterface $field, array $settings): void {
    if ($field->getType() === 'custom') {
      $custom_items = $this->customFieldTypeManager->getCustomFieldItems($field->getSettings());
      $entity_type_id = $field->getTargetEntityTypeId();
      $bundle = $field->getTargetBundle();
      $field_name = $field->getName();
      $form['subfields'] = [
        '#type' => 'container',
        '#title' => $this->t('Subfield settings'),
        '#weight' => 10,
        '#states' => [
          'visible' => [
            ':input[name="settings[field_config][' . $entity_type_id . '][' . $bundle . '][' . $field_name . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      // Sort fields alphabetically by name.
      ksort($custom_items);
      foreach ($custom_items as $name => $custom_item) {
        $placeholder = u($custom_item->getName())
          ->camel()
          ->toString();
        // Allow users to enable and disable the field.
        $form['subfields'][(string) $name] = [
          '#type' => 'details',
          '#title' => $this->t('@label (@name)', ['@label' => $custom_item->getLabel(), '@name' => $name]),
        ];
        $form['subfields'][$name]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable field'),
          '#default_value' => $settings['subfields'][$name]['enabled'] ?? TRUE,
        ];
        $form['subfields'][$name]['name_sdl'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Schema field name'),
          '#default_value' => $settings['subfields'][$name]['name_sdl'] ?? NULL,
          '#placeholder' => $placeholder,
          '#description' => $this->t('Leave blank to use automatically generated name.'),
          '#element_validate' => [
            ['\Drupal\graphql_compose\Form\SchemaForm', 'validateNullable'],
            [static::class, 'validateNameSdl'],
          ],
          '#maxlength' => 255,
          '#size' => 20,
        ];

        // A sdl rename is required if field name starts with a number.
        // https://www.drupal.org/project/graphql_compose/issues/3409260
        if (preg_match('/^[0-9]/', $placeholder)) {
          $form['subfields'][$name]['name_sdl']['#element_validate'][] = [
            '\Drupal\graphql_compose\Form\SchemaForm',
            'validateNameSdlRequired',
          ];
          $form['subfields'][$name]['name_sdl']['#states']['required'] = [
            ':input[name="settings[field_config][' . $entity_type_id . '][' . $bundle . '][' . $field_name . '][subfields][' . $name . '][enabled]"]' => ['checked' => TRUE],
          ];
        }
      }
    }
  }

  /**
   * Custom validation for the name_sdl field to allow underscores.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The form.
   */
  public static function validateNameSdl(array &$element, FormStateInterface &$form_state, array $form): void {
    $value = $form_state->getValue($element['#parents'], '');
    $value = is_string($value) ? trim($value) : $value;
    $values = $form_state->getValues();

    $enabled = NestedArray::getValue(
      $values,
      [...array_slice($element['#parents'], 0, -1), 'enabled']
    );

    if ($enabled && $value && !preg_match('/^[a-z]([A-Za-z0-9_])*$/', $value)) {
      $message = t('@name must start with a lowercase letter and contain only letters and numbers.', [
        '@name' => $element['#title'] ?? 'Field name',
      ]);
      $form_state->setError($element, $message);
    }
  }

}
