<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Plugin implementation of the 'entity_reference_autocomplete' widget.
 */
#[CustomFieldWidget(
  id: 'entity_reference_autocomplete',
  label: new TranslatableMarkup('Autocomplete'),
  category: new TranslatableMarkup('Reference'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceAutocompleteWidget extends EntityReferenceWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
      'size' => 60,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $settings['match_operator'],
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
    ];
    $element['match_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $settings['match_limit'],
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];
    $element['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of textfield'),
      '#default_value' => $settings['size'],
      '#min' => 1,
      '#required' => TRUE,
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $settings['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $field_settings = $field->getFieldSettings();
    $entity = $items->getEntity();
    $target_type = $field->getTargetType();
    if (!isset($field_settings['handler'])) {
      $field_settings['handler'] = 'default:' . $target_type;
    }

    // Append the match operation to the selection settings.
    $selection_settings = $field_settings['handler_settings'] + [
      'match_operator' => $settings['match_operator'],
      'match_limit' => $settings['match_limit'],
    ];

    // Append the entity if it is already created.
    if (!$entity->isNew()) {
      $selection_settings['entity'] = $entity;
    }

    if (isset($selection_settings['target_bundles']) && $selection_settings['target_bundles'] === []) {
      $selection_settings['target_bundles'] = NULL;
    }

    $element += [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_type,
      '#selection_handler' => $field_settings['handler'],
      '#selection_settings' => $selection_settings,
      // Entity reference field items are handling validation themselves via
      // the 'ValidReference' constraint.
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#default_value' => NULL,
      '#size' => $settings['size'],
      '#placeholder' => $settings['placeholder'],
    ];

    if (isset($element['#default_value'])) {
      $referenced_entity = $this->entityTypeManager
        ->getStorage($target_type)
        ->load($element['#default_value']);
      $element['#default_value'] = $referenced_entity;
    }

    if ($bundle = $this->getAutocreateBundle($field_settings['handler_settings'], $target_type, $field)) {
      $element['#autocreate'] = [
        'bundle' => $bundle,
        'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : $this->currentUser->id(),
      ];
    }

    return ['target_id' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    // The entity_autocomplete form element returns an array when an entity
    // was "autocreated", so we need to move it up a level.
    if (empty($value['target_id'])) {
      return NULL;
    }
    if (is_array($value['target_id'])) {
      $value += $value['target_id'];
      unset($value['target_id']);
    }

    return $value;
  }

  /**
   * Returns the name of the bundle which will be used for autocreated entities.
   *
   * @param array $handler_settings
   *   The field handler settings.
   * @param string $target_type
   *   The target_type setting for the field.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field.
   *
   * @return string|null
   *   The bundle name. If autocreate is not active, NULL will be returned.
   */
  protected function getAutocreateBundle(array $handler_settings, string $target_type, CustomFieldTypeInterface $field): ?string {
    $bundle = NULL;
    $auto_create = $handler_settings['auto_create'] ?? FALSE;
    if ($auto_create) {
      $target_bundles = $handler_settings['target_bundles'];
      // If there's no target bundle at all, use the target_type. It's the
      // default for bundleless entity types.
      if (empty($target_bundles)) {
        $bundle = $target_type;
      }
      // If there's only one target bundle, use it.
      elseif (count($target_bundles) == 1) {
        $bundle = reset($target_bundles);
      }
      // If there's more than one target bundle, use the autocreate bundle
      // stored in selection handler settings.
      elseif (!$bundle = $handler_settings['auto_create_bundle']) {
        // If no bundle has been set as auto create target means that there is
        // an inconsistency in entity reference field settings.
        trigger_error(sprintf(
          "The 'Create referenced entities if they don't already exist' option is enabled but a specific destination bundle is not set. You should re-visit and fix the settings of the '%s' (%s) field.",
          $field->getLabel(),
          $field->getName()
        ), E_USER_WARNING);
      }
    }

    return $bundle;
  }

  /**
   * Returns the options for the match operator.
   *
   * @return String[]
   *   List of options.
   */
  protected function getMatchOperatorOptions(): array {
    return [
      'STARTS_WITH' => (string) $this->t('Starts with'),
      'CONTAINS' => (string) $this->t('Contains'),
    ];
  }

}
