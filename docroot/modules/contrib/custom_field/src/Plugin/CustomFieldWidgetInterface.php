<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for custom field Type plugins.
 */
interface CustomFieldWidgetInterface extends PluginInspectionInterface {

  /**
   * Defines the widget settings for this plugin.
   *
   * @return array{label: string, translatable: bool, settings: array<string, mixed>}
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultSettings(): array;

  /**
   * Returns a form for the widget settings for this custom field type.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return array<string, mixed>
   *   The form definition for the widget settings.
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array;

  /**
   * Returns the Custom field item widget as form array.
   *
   * Called from the Custom field widget plugin formElement method.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\custom_field\Plugin\Field\FieldType\CustomItem> $items
   *   Array of default values for this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param array<string, mixed> $element
   *   A form element array containing basic properties for the widget:
   *   - #field_parents: The 'parents' space for the field in the form. Most
   *       widgets can simply overlook this property. This identifies the
   *       location where the field values are placed within
   *       $form_state->getValues(), and is used to access processing
   *       information for the field through the getWidgetState() and
   *       setWidgetState() methods.
   *   - #title: The sanitized element label for the field, ready for output.
   *   - #description: The sanitized element description for the field, ready
   *     for output.
   *   - #required: A Boolean indicating whether the element value is required;
   *     for required multiple value fields, only the first widget's values are
   *     required.
   *   - #delta: The order of this item in the array of sub-elements; see $delta
   *     above.
   * @param array<string, mixed> $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return array<string, mixed>
   *   The form elements for a single widget for this field.
   *
   * @see \Drupal\Core\Field\WidgetInterface::formElement()
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array;

  /**
   * Massages the form value into the format expected for field values.
   *
   * @param mixed $value
   *   The submitted form value produced by the widget.
   * @param array<string, mixed> $column
   *   The storage column for extra properties.
   *
   * @return mixed
   *   The new value.
   */
  public function massageFormValue(mixed $value, array $column): mixed;

  /**
   * Returns if the widget can be used for the provided field.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The custom field item.
   *
   * @return bool
   *   TRUE if the widget can be used, FALSE otherwise.
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool;

  /**
   * Returns an array of dependencies for the widget.
   *
   * @return array
   *   An array of dependencies.
   */
  public function calculateWidgetDependencies(): array;

  /**
   * Returns an array of changed settings for the parent method to act on.
   *
   * @param array<string, mixed> $dependencies
   *   An array of dependencies for the subfield to evaluate.
   *
   * @return array<string, mixed>
   *   An array of settings for parent to update.
   */
  public function onWidgetDependencyRemoval(array $dependencies): array;

}
