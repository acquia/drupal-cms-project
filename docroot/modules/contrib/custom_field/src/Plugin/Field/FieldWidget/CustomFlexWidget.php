<?php

namespace Drupal\custom_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'custom_flex' widget.
 */
#[FieldWidget(
  id: 'custom_flex',
  label: new TranslatableMarkup('Flexbox'),
  field_types: [
    'custom',
  ],
  weight: 0,
)]
class CustomFlexWidget extends CustomWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'breakpoint' => '',
      'columns' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['#tree'] = TRUE;
    $elements['#attached']['library'][] = 'custom_field/custom-field-flex';
    $elements['#attached']['library'][] = 'custom_field/custom-field-flex-admin';
    $custom_items = $this->getCustomFieldItems($form_state);

    $elements['columns'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Column settings'),
      '#description' => $this->t('Select the number of columns for each form element. A value of <em>auto</em> will size the column based on the natural width of content within it.'),
      '#description_display' => 'before',
    ];

    $elements['columns']['prefix'] = [
      '#markup' => '<div class="custom-field-row custom-field-flex--widget-settings">',
    ];

    $columns = $this->getSettings()['columns'];
    foreach ($custom_items as $name => $custom_item) {
      $plugin_id = $custom_item->getPluginId();
      // The uuid widget type is a hidden field.
      if ($plugin_id == 'uuid') {
        continue;
      }
      $elements['columns'][$name] = [
        '#type' => 'select',
        '#title' => $custom_item->getLabel(),
        '#options' => $this->columnOptions(),
        '#wrapper_attributes' => [
          'class' => ['custom-field-col'],
        ],
        '#attributes' => [
          'class' => ['custom-field-col__field'],
        ],
      ];
      if (isset($columns[$name])) {
        $elements['columns'][$name]['#default_value'] = $columns[$name];
        $elements['columns'][$name]['#wrapper_attributes']['class'][] = 'custom-field-col-' . $columns[$name];
      }
    }

    $elements['columns']['suffix'] = [
      '#markup' => '</div>',
    ];

    $elements['breakpoint'] = [
      '#type' => 'select',
      '#title' => $this->t('Stack items on:'),
      '#description' => $this->t('The device width in which the columns are set to full width and stack on top of one another.'),
      '#options' => $this->breakpointOptions(),
      '#default_value' => $this->getSetting('breakpoint'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $columns = 'Automatic';
    if (!empty($this->getSettings()['columns'])) {
      $columns = implode(' | ', $this->getSettings()['columns']);
    }
    $summary[] = $this->t('Column settings: @columns', ['@columns' => $columns]);
    $summary[] = $this->t('Stack on: @breakpoint', ['@breakpoint' => $this->breakpointOptions($this->getSetting('breakpoint'))]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#attached']['library'][] = 'custom_field/custom-field-flex';
    $fields = $this->getSetting('fields') ?? [];
    $classes = ['custom-field-row'];
    if ($this->getSetting('breakpoint')) {
      $classes[] = 'custom-field-flex--stack-' . $this->getSetting('breakpoint');
    }
    // Using markup since we can't nest values because the field api expects
    // subfields to be at the top-level.
    $element['wrapper_prefix']['#markup'] = '<div class="' . implode(' ', $classes) . '">';
    $columns = $this->getSettings()['columns'];
    $custom_items = $this->getCustomFieldItems($form_state);

    foreach ($custom_items as $name => $custom_item) {
      $settings = $fields[$name] ?? [];
      $data_type = $custom_item->getDataType();
      $type = $settings['type'] ?? $custom_item->getDefaultWidget();
      if (!in_array($type, $this->customFieldWidgetManager->getWidgetsForField($custom_item->getPluginId()))) {
        $type = $custom_item->getDefaultWidget();
      }
      /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetInterface $widget_plugin */
      $widget_plugin = $this->customFieldWidgetManager->createInstance((string) $type, ['settings' => $settings]);
      $element[$name] = $widget_plugin->widget($items, $delta, $element, $form, $form_state, $custom_item);
      $attributes = $this->getAttributesKey($custom_item, $type);
      $column_class = "custom-field-$name custom-field-col";
      $column_class .= isset($columns[$name]) ? " custom-field-col-$columns[$name]" : ' custom-field-col-auto';
      $entity_reference_widgets = [
        'entity_reference_autocomplete',
        'entity_reference_radios',
        'entity_reference_select',
      ];

      // For the file widget, we assign to the outer ajax wrapper div.
      if (isset($element[$name]['#type']) && $element[$name]['#type'] === 'managed_file') {
        $element[$name]['#column_class'] = $column_class;
        $element[$name]['#after_build'][] = [$this, 'callManagedFileAfterBuild'];
      }

      // Entity reference widgets need class on the target_id element.
      elseif (in_array($type, $entity_reference_widgets)) {
        $element[$name]['target_id'][$attributes]['class'][] = $column_class;
      }

      // The datetime widgets have different wrapper types.
      elseif ($data_type === 'datetime' && in_array($type, ['datetime_default', 'datetime_local'])) {
        $datetime_type = $custom_item->getDatetimeType();
        if (($datetime_type === DateTimeType::DATETIME_TYPE_DATE || $type === 'datetime_local') && !isset($element[$name]['timezone'])) {
          $element[$name]['value']['#wrapper_attributes']['class'][] = $column_class;
        }
        else {
          $element[$name][$attributes]['class'][] = $column_class;
        }
      }
      // For the date range widget, we assign to the outer ajax wrapper div.
      elseif ($data_type === 'daterange' && $type === 'daterange_default') {
        $element[$name]['#column_class'] = $column_class;
        $element[$name]['#after_build'][] = [$this, 'callDateRangeAfterBuild'];
      }

      else {
        // The duration input element is wrapped in a fieldset.
        if ($data_type === 'duration' && $settings['duration_element'] === 'input') {
          $attributes = '#attributes';
        }
        $element[$name][$attributes]['class'][] = $column_class;
      }
    }

    $element['wrapper_suffix']['#markup'] = '</div>';

    return $element;
  }

  /**
   * Closure function to pass arguments to dateRangeAfterBuild().
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The element array.
   */
  public function callDateRangeAfterBuild(array $element, FormStateInterface $form_state): array {
    $column = $element['#column_class'];
    return static::dateRangeAfterBuild($element, $form_state, $column);
  }

  /**
   * Closure function to pass arguments to managedFileAfterBuild().
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The element array.
   */
  public function callManagedFileAfterBuild(array $element, FormStateInterface $form_state): array {
    $column = $element['#column_class'];
    return static::managedFileAfterBuild($element, $form_state, $column);
  }

  /**
   * After build function to add class to file outer ajax wrapper div.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $column
   *   The column class.
   *
   * @return array<string, mixed>
   *   The modified form element.
   */
  public static function managedFileAfterBuild(array $element, FormStateInterface $form_state, string $column): array {
    if (preg_match('/id="([^"]*ajax-wrapper[^"]*)"/', $element['#prefix'], $matches)) {
      $id_attribute = $matches[0];
      // Check if the class attribute exists.
      if (str_contains($element['#prefix'], 'class="')) {
        // If class exists, append the new class.
        $element['#prefix'] = str_replace('class="', 'class="' . $column . ' ', $element['#prefix']);
      }
      else {
        // If no class attribute exists, insert one after the id attribute.
        $element['#prefix'] = str_replace($id_attribute, $id_attribute . ' class="' . $column . '"', $element['#prefix']);
      }
    }
    return $element;
  }

  /**
   * After build function to add class to date range outer ajax wrapper div.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $column
   *   The column class.
   *
   * @return array<string, mixed>
   *   The modified form element.
   */
  public static function dateRangeAfterBuild(array $element, FormStateInterface $form_state, string $column): array {
    $id_attribute = '';
    if (preg_match('/id="([^"]+)"/', $element['#prefix'], $matches)) {
      $id_attribute = $matches[0];
    }
    // Check if the class attribute exists.
    if (str_contains($element['#prefix'], 'class="')) {
      // If class exists, append the new class.
      $element['#prefix'] = str_replace('class="', 'class="' . $column . ' ', $element['#prefix']);
    }
    else {
      // If no class attribute exists, insert one after the id attribute.
      $element['#prefix'] = str_replace($id_attribute, $id_attribute . ' class="' . $column . '"', $element['#prefix']);
    }

    return $element;
  }

  /**
   * Determine which attributes to use based on the plugin type.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The custom field item.
   * @param string $type
   *   The widget type.
   *
   * @return string
   *   The attribute key string.
   */
  protected function getAttributesKey(CustomFieldTypeInterface $custom_item, string $type): string {
    $field_settings = $custom_item->getFieldSettings();
    $attribute_types = [
      'color_boxes',
      'media_library_widget',
      'viewfield_select',
      'entity_reference_radios',
      'radios',
      'datetime_datelist',
      'datetime_default',
      'datetime_local',
      'time_range',
      'url',
      'link_default',
      'linkit_url',
      'linkit',
    ];

    if (in_array($type, $attribute_types)) {
      return '#attributes';
    }

    switch ($custom_item->getPluginId()) {
      case 'string_long':
        $formatted = $field_settings['formatted'] ?? FALSE;
        return $formatted ? '#attributes' : '#wrapper_attributes';

      default:
        return '#wrapper_attributes';
    }
  }

  /**
   * Get the field storage definition.
   */
  public function getFieldStorageDefinition(): FieldStorageDefinitionInterface {
    return $this->fieldDefinition->getFieldStorageDefinition();
  }

  /**
   * The options for columns.
   *
   * @param string|null $option
   *   The option key.
   *
   * @return array<string|int, TranslatableMarkup>|string
   *   The options or option.
   */
  public function columnOptions(?string $option = NULL): array|string {
    $options = [
      'auto' => $this->t('Auto'),
      1 => $this->t('1 column'),
      2 => $this->t('2 columns'),
      3 => $this->t('3 columns'),
      4 => $this->t('4 columns'),
      5 => $this->t('5 columns'),
      6 => $this->t('6 columns'),
      7 => $this->t('7 columns'),
      8 => $this->t('8 columns'),
      9 => $this->t('9 columns'),
      10 => $this->t('10 columns'),
      11 => $this->t('11 columns'),
      12 => $this->t('12 columns'),
    ];
    // @todo Better move this logic out of this function, so this function can
    // have single return type.
    if (!is_null($option)) {
      return $options[$option] ?? '';
    }

    return $options;
  }

  /**
   * The options for breakpoints.
   *
   * @param string|null $option
   *   The option key.
   *
   * @return array<string, TranslatableMarkup>|string
   *   The options or option.
   */
  public function breakpointOptions(?string $option = NULL): array|string {
    $options = [
      '' => $this->t("Don't stack"),
      'medium' => $this->t('Medium (less than 769px)'),
      'small' => $this->t('Small (less than 601px)'),
    ];
    // @todo Better move this logic out of this function, so this function can
    // have single return type.
    if (!is_null($option)) {
      return $options[$option] ?? '';
    }

    return $options;
  }

}
