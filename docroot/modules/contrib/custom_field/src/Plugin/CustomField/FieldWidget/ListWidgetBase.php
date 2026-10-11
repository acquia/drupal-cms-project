<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;

/**
 * Base plugin class for list custom field widgets.
 */
class ListWidgetBase extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'empty_option' => '- Select -',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $allowed_values = $field->getFieldSetting('allowed_values') ?? [];

    $options = [];
    foreach ($allowed_values as $option) {
      $options[$option['key']] = $option['label'];
    }

    // Add our widget type and additional properties and return.
    return [
      '#type' => 'select',
      '#options' => $options,
    ] + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['empty_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty option'),
      '#description' => $this->t('Option to show when field is not required.'),
      '#default_value' => $settings['empty_option'],
      '#required' => TRUE,
    ];

    return $element;
  }

}
