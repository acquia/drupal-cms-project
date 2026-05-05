<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for component prop widget plugins.
 */
interface PropWidgetInterface extends PluginInspectionInterface {

  const SPACE_CHARACTER = '&nbsp;';

  const EMPTY_VALUE = '<empty>';

  const BOOLEAN_TRUE = 'Yes';

  const BOOLEAN_FALSE = 'No';

  /**
   * Defines the widget settings for this plugin.
   *
   * @return array{label: string, translatable: bool, settings: array<string, mixed>}
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultSettings(): array;

  /**
   * Returns settings summary for the prop widget.
   *
   * @param string $property
   *   The property name.
   * @param mixed $value
   *   The value.
   *
   * @return array
   *   The settings summary.
   */
  public function settingsSummary(string $property, mixed $value): array;

  /**
   * Returns a string of spaces for indentation.
   *
   * @param int $indent
   *   The number of spaces to indent.
   *
   * @return string
   *   The string of spaces.
   */
  public function space(int $indent = 2): string;

  /**
   * Returns the Custom field item widget as form array.
   *
   * Called from the Custom field widget plugin widget method.
   *
   * @param array<string, mixed> $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param mixed $value
   *   The default value of the prop.
   * @param bool $required
   *   Whether the prop is required.
   *
   * @return array<string, mixed>
   *   The form elements for a single widget for this field.
   *
   * @see \Drupal\Core\Field\WidgetInterface::formElement()
   */
  public function widget(array &$form, FormStateInterface $form_state, mixed $value, bool $required): array;

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

  /**
   * Massage the value before saving to config.
   *
   * @param array<string, mixed> $value
   *   The value to massage.
   *
   * @return mixed
   *   The massaged value.
   */
  public function massageValue(array $value): mixed;

}
