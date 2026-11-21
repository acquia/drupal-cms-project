<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface definition for field widget plugins.
 *
 * This interface details the methods that most plugin implementations will want
 * to override. See Drupal\Core\Field\WidgetBaseInterface for base
 * wrapping methods that should most likely be inherited directly from
 * Drupal\Core\Field\WidgetBase.
 *
 * @ingroup field_widget
 */
interface CustomFieldFormatterInterface extends PluginInspectionInterface {

  /**
   * Returns the formatter default settings.
   *
   * @return array<string, mixed>
   *   An array of default settings.
   */
  public static function defaultSettings(): array;

  /**
   * Returns a form to configure settings for the widget.
   *
   * Invoked from \Drupal\field_ui\Form\EntityDisplayFormBase to allow
   * administrators to configure the widget. The field_ui module takes care of
   * handling submitted form values.
   *
   * @param array<string, mixed> $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form definition for the widget settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array;

  /**
   * Returns a short summary for the current widget settings.
   *
   * If an empty result is returned, a UI can still be provided to display
   * a settings form in case the widget has configurable settings.
   *
   * @return string[]
   *   A short summary of the widget settings.
   */
  public function settingsSummary(): array;

  /**
   * Returns the field value as formatted.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param mixed $value
   *   The value to format.
   *
   * @return mixed
   *   The formatted value.
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed;

  /**
   * Returns calculated dependencies for formatter plugin.
   *
   * @param array<string, mixed> $settings
   *   The formatter settings for the plugin.
   *
   * @return array<string, mixed>
   *   The dependencies.
   */
  public function calculateFormatterDependencies(array $settings): array;

  /**
   * Returns the changed settings for formatter plugin.
   *
   * @param array<string, mixed> $dependencies
   *   The formatter dependencies array.
   * @param array<string, mixed> $settings
   *   The formatter settings for the plugin.
   *
   * @return array
   *   The changed formatter settings.
   */
  public function onFormatterDependencyRemoval(array $dependencies, array $settings): array;

  /**
   * Returns if the formatter can be used for the provided field.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The custom field type.
   *
   * @return bool
   *   TRUE if the formatter can be used, FALSE otherwise.
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool;

}
