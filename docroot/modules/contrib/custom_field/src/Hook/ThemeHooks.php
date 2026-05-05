<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Provides hooks related to config schemas.
 */
class ThemeHooks {

  /**
   * Constructs a ThemeHooks object.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    $item = ['render element' => 'elements'];
    return [
      'custom_field' => $item,
      'custom_field_item' => $item,
      'custom_field_hierarchical_formatter' => [
        'variables' => [
          'terms' => [],
          'wrapper' => '',
          'separator' => ' » ',
          'link' => FALSE,
        ],
        'file' => 'custom_field_hierarchical_formatter.theme.inc',
      ],
      'custom_field_flex_wrapper' => $item,
      'custom_field_daterange' => $item,
      'custom_field_time_range' => $item,
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_custom_field')]
  public function themeSuggestionsCustomField(array $variables): array {
    return [
      'custom_field__' . $variables['elements']['#field_name'],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_custom_field_item')]
  public function themeSuggestionsCustomFieldItem(array $variables): array {
    $hook = 'custom_field_item';
    return [
      $hook . '__' . $variables['elements']['#field_name'],
      $hook . '__' . $variables['elements']['#field_name'] . '__' . $variables['elements']['#type'],
      $hook . '__' . $variables['elements']['#field_name'] . '__' . $variables['elements']['#type'] . '__' . $variables['elements']['#name'],
      $hook . '__' . $variables['elements']['#field_name'] . '__' . $variables['elements']['#name'],
    ];
  }

  /**
   * Implements hook_preprocess_field_multiple_value_form().
   */
  #[Hook('preprocess_field_multiple_value_form')]
  public function preprocessFieldMultipleValueForm(array &$variables): void {
    if (empty($variables['element']['#custom_field_header'])) {
      return;
    }

    $button = $variables['element']['#custom_field_header'];
    $cardinality = $variables['element']['#cardinality'];
    $table_class = $button['#table_class'];
    if (!empty($variables['table']['#header']) && isset($variables['table']['#rows'][0])) {
      $variables['table']['#attributes']['class'][] = 'custom-field-multi';
      $variables['table']['#attributes']['class'][] = 'field-table--' . $table_class;
      $variables['table']['#header'][1]['class'][] = 'custom-field-actions';

      // Check for the 'Simple add more' module.
      $sam_enabled = $this->moduleHandler->moduleExists('sam');
      // If there's a weight column, we want the header button aligned with it.
      if ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $sam_enabled) {
        $variables['table']['#header'][1]['data'] = [
          'button' => $button,
        ];
      }
      else {
        $variables['table']['#header'][0]['class'][] = 'custom-field-actions-header';
        $variables['table']['#header'][0]['data'][] = [
          'button' => $button,
        ];
      }
    }
  }

}
