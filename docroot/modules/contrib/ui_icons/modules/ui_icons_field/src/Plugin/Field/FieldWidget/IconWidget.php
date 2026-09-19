<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\ui_icons_field\IconFieldTrait;
use Drupal\ui_icons\IconSearch;

/**
 * Plugin implementation of the 'icon_widget' widget.
 */
#[FieldWidget(
  id: 'icon_widget',
  label: new TranslatableMarkup('Icon'),
  field_types: ['ui_icon'],
)]
class IconWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  use IconFieldTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'icon_selector' => 'icon_autocomplete',
      'result_format' => 'list',
      'max_result' => IconSearch::SEARCH_RESULT,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['icon_selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon selector'),
      '#options' => $this->getPickerOptions(),
      '#default_value' => $this->getSetting('icon_selector'),
    ];

    $element['result_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Result format'),
      '#options' => $this->getAutocompleteFormat(),
      '#default_value' => $this->getSetting('result_format'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][icon_selector]"]' => ['value' => 'icon_autocomplete'],
        ],
      ],
    ];

    $element['max_result'] = [
      '#type' => 'number',
      '#min' => 2,
      '#max' => IconSearch::SEARCH_RESULT_MAX,
      '#title' => $this->t('Maximum results'),
      '#default_value' => $this->getSetting('max_result'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $icon_selector = $this->getSetting('icon_selector');
    $result_format = $this->getSetting('result_format');
    $summary[] = $this->t('Selector: @type', ['@type' => $this->getPickerOptions()[$icon_selector] ?? '']);
    if ($icon_selector === 'icon_autocomplete') {
      $summary[] = $this->t('Result format: @format', ['@format' => $this->getAutocompleteFormat()[$result_format] ?? 'list']);
    }
    $summary[] = $this->t('Max results: @results', ['@results' => $this->getSetting('max_result')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];

    $icon_selector = $this->getSetting('icon_selector');
    $allowed_icon_pack = array_filter($this->fieldDefinition->getSetting('allowed_icon_pack') ?? []);
    $element['value'] = [
      '#type' => $icon_selector,
      '#title' => $cardinality === 1 ? $this->fieldDefinition->getLabel() : $this->t('Icon'),
      '#description' => $element['#description'] ?? '',
      '#allowed_icon_pack' => $allowed_icon_pack,
      '#required' => $element['#required'] ?? FALSE,
      '#show_settings' => FALSE,
      '#default_value' => NULL,
      '#max_result' => $this->getSetting('max_result'),
    ];

    if ($icon_selector === 'icon_autocomplete') {
      $element['value']['#result_format'] = $this->getSetting('result_format');
    }

    if ($item && $item->target_id) {
      $element['value']['#default_value'] = $item->target_id;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    // Only store the icon ID from the FormElement result.
    // @todo #return_id by default?
    foreach ($values as &$item) {
      if (empty($item['value']['icon']) || !$item['value']['icon'] instanceof IconDefinitionInterface) {
        unset($item['value']);
        continue;
      }

      $icon = $item['value']['icon'];
      $item['target_id'] = $icon->getId();
      unset($item['value']);
    }

    return $values;
  }

}
