<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_icons\IconSearch;

/**
 * Provides a trait for icon link widgets.
 *
 * There is voluntarily no parent:: calls as this trait could be used in
 * conjunction with LinkWithAttributesWidgetTrait that already call it.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
trait IconLinkWidgetTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'allowed_icon_pack' => [],
      'icon_selector' => 'icon_autocomplete',
      'result_format' => 'list',
      'max_result' => IconSearch::SEARCH_RESULT,
      'icon_required' => TRUE,
      'icon_position' => FALSE,
      // Show settings is used by menu link implementation.
      // there is no settings form visible as it must be set only in the field
      // definition.
      'show_settings' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = [];
    $elements['icon_selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon selector'),
      '#options' => $this->getPickerOptions(),
      '#default_value' => $this->getSetting('icon_selector'),
    ];

    $elements['result_format'] = [
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

    $elements['max_result'] = [
      '#type' => 'number',
      '#min' => 2,
      '#max' => IconSearch::SEARCH_RESULT_MAX,
      '#title' => $this->t('Maximum results'),
      '#default_value' => $this->getSetting('max_result'),
    ];

    $elements['icon_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon required'),
      '#description' => $this->t('Set the icon selection mandatory, will be applied only if the link itself is required.'),
      '#default_value' => (bool) $this->getSetting('icon_required'),
    ];

    $elements['icon_position'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow icon display position selection'),
      '#description' => $this->t('If selected, a "position" select will be made available. Default is from the display of this field.'),
      '#default_value' => (bool) $this->getSetting('icon_position'),
    ];

    $options = $this->pluginManagerIconPack->listIconPackOptions(TRUE);
    $elements['allowed_icon_pack'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed icon packs'),
      '#description' => $this->t('If none are selected, all will be allowed.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('allowed_icon_pack'),
      '#multiple' => TRUE,
    ];

    if (count($options) > 10) {
      $elements['allowed_icon_pack']['#prefix'] = '<details>';
      $elements['allowed_icon_pack']['#prefix'] .= '<summary>' . $elements['allowed_icon_pack']['#title'] . '</summary>';
      $elements['allowed_icon_pack']['#suffix'] = '</details>';
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $settings = $this->getSettings();

    $allowed_icon_pack = array_filter($settings['allowed_icon_pack']);

    if (!empty($allowed_icon_pack)) {
      $labels = $this->pluginManagerIconPack->listIconPackOptions();
      $list = array_intersect_key($labels, $allowed_icon_pack);
      $summary[] = $this->t('With Icon set: @set', ['@set' => implode(', ', $list)]);
    }
    else {
      $summary[] = $this->t('All icon sets available for selection');
    }

    $icon_selector = $this->getSetting('icon_selector');
    $summary[] = $this->t('Selector: @type', ['@type' => $this->getPickerOptions()[$icon_selector] ?? '']);

    $result_format = $this->getSetting('result_format');
    if ($icon_selector === 'icon_autocomplete') {
      $summary[] = $this->t('Result format: @format', ['@format' => $this->getAutocompleteFormat()[$result_format] ?? 'list']);
    }
    $summary[] = $this->t('Max results: @results', ['@results' => $this->getSetting('max_result')]);

    if (TRUE === (bool) $settings['icon_required']) {
      $summary[] = $this->t('Icon is required');
    }
    else {
      $summary[] = $this->t('Icon is not required');
    }

    if ((bool) $settings['icon_position']) {
      $summary[] = $this->t('Can set icon display');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];

    $options = $item->get('options')->getValue() ?? [];
    $icon_full_id = $options['icon']['target_id'] ?? NULL;

    $icon_display = $options['icon_display'] ?? 'icon_only';
    $allowed_icon_pack = array_filter($this->getSetting('allowed_icon_pack') ?? []);
    $label = $this->fieldDefinition->getLabel() ?? $this->t('Link');
    $field_name = $this->fieldDefinition->getName();

    $icon_selector = $this->getSetting('icon_selector');
    $element['icon'] = [
      '#type' => $icon_selector,
      '#title' => $this->t('@name icon', ['@name' => $label]),
      '#return_id' => TRUE,
      '#default_value' => $icon_full_id,
      '#max_result' => $this->getSetting('max_result'),
      '#allowed_icon_pack' => $allowed_icon_pack,
      // Show settings is used by menu link implementation.
      '#show_settings' => $settings['show_settings'] ?? FALSE,
      '#required' => $element['#required'] ? $settings['icon_required'] : FALSE,
      // Put the parent to allow saving under `options`.
      '#parents' => array_merge($element['#field_parents'], [
        $field_name,
        $delta,
        'options',
        'icon',
      ]),
    ];

    if ($icon_selector === 'icon_autocomplete') {
      $element['icon']['#result_format'] = $this->getSetting('result_format');
    }

    if (isset($options['icon']['settings'])) {
      $element['icon']['#default_settings'] = $options['icon']['settings'];
    }

    if (FALSE === $settings['icon_position']) {
      return $element;
    }

    $element['icon_display'] = [
      '#type' => 'select',
      '#title' => $this->t('@name icon display', ['@name' => $label]),
      '#description' => $this->t('Choose display for this icon link.'),
      '#default_value' => $icon_display,
      '#options' => $this->getDisplayPositions(),
      '#states' => [
        'visible' => [
          ':input[name="' . $field_name . '[' . $delta . '][options][icon][icon_id]"]' => ['empty' => FALSE],
        ],
      ],
      // Put the parent to allow saving under `options`.
      '#parents' => array_merge($element['#field_parents'], [
        $field_name,
        $delta,
        'options',
        'icon_display',
      ]),
    ];

    return $element;
  }

}
