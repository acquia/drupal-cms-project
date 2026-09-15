<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\Attribute;
use Drupal\custom_field\TagManagerInterface;

/**
 * Provides hooks related to config schemas.
 */
class ThemeHooks {

  /**
   * Constructs a ThemeHooks object.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    $item = ['render element' => 'elements'];
    return [
      'custom_field' => $item + [
        'initial preprocess' => static::class . ':preprocessCustomField',
      ],
      'custom_field_item' => $item + [
        'initial preprocess' => static::class . ':preprocessCustomFieldItem',
      ],
      'custom_field_hierarchical_formatter' => [
        'variables' => [
          'terms' => [],
          'wrapper' => '',
          'separator' => ' » ',
          'link' => FALSE,
        ],
        'initial preprocess' => static::class . ':preprocessCustomFieldHierarchicalFormatter',
      ],
      'custom_field_flex_wrapper' => $item,
      'custom_field_daterange' => $item + [
        'initial preprocess' => static::class . ':preprocessCustomFieldDaterange',
      ],
      'custom_field_time_range' => $item + [
        'initial preprocess' => static::class . ':preprocessCustomFieldTimeRange',
      ],
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

  /**
   * Prepares variables for custom-field template.
   */
  public function preprocessCustomField(array &$variables): void {
    $variables['items'] = $variables['elements']['#items'];
    $variables['field_name'] = $variables['elements']['#field_name'];
    // Set the item attributes.
    foreach ($variables['elements']['#items'] as &$item) {
      // Attributes are optional, so we check if it's set first and process
      // appropriately.
      if (isset($item['attributes'])) {
        $item['attributes'] = new Attribute($item['attributes']);
      }
      else {
        $item['attributes'] = new Attribute();
      }
    }
  }

  /**
   * Prepares variables for custom-field-item template.
   */
  public function preprocessCustomFieldItem(array &$variables): void {
    $wrappers = $variables['elements']['#wrappers'];
    // Set wrapper classes.
    if (!empty($wrappers['field_wrapper_classes'])) {
      $wrapper_classes = explode(' ', $wrappers['field_wrapper_classes']);
      foreach ($wrapper_classes as $class) {
        $variables['attributes']['class'][] = Html::cleanCssIdentifier($class, []);
      }
    }
    // Set field classes.
    if (!empty($wrappers['field_classes'])) {
      $field_classes = explode(' ', $wrappers['field_classes']);
      foreach ($field_classes as $class) {
        $variables['content_attributes']['class'][] = Html::cleanCssIdentifier($class, []);
      }
    }
    // Set label classes.
    if (!empty($wrappers['label_classes'])) {
      $label_classes = explode(' ', $wrappers['label_classes']);
      foreach ($label_classes as $class) {
        $variables['title_attributes']['class'][] = Html::cleanCssIdentifier($class, []);
      }
    }

    $variables['display_label_tag'] = $wrappers['label_tag'] !== TagManagerInterface::NO_MARKUP_VALUE;
    $variables['display_field_tag'] = $wrappers['field_tag'] !== TagManagerInterface::NO_MARKUP_VALUE;
    $variables['display_field_wrapper_tag'] = $wrappers['field_wrapper_tag'] !== TagManagerInterface::NO_MARKUP_VALUE;
    $variables['field_wrapper_tag'] = $wrappers['field_wrapper_tag'];
    $variables['field_tag'] = $wrappers['field_tag'];
    $variables['label_tag'] = $wrappers['label_tag'];
    $variables['label'] = $variables['elements']['#label'];
    $variables['label_display'] = $variables['elements']['#label_display'];
    $variables['label_hidden'] = ($variables['elements']['#label_display'] == 'hidden');
    $variables['value'] = $variables['elements']['#value'];
    $variables['name'] = $variables['elements']['#name'];
    $variables['type'] = $variables['elements']['#type'];
    $variables['field_name'] = $variables['elements']['#field_name'];
  }

  /**
   * Prepares variables for custom-field-daterange template.
   */
  public function preprocessCustomFieldDaterange(array &$variables): void {
    $variables['all_day'] = $variables['elements']['all_day'] ?? NULL;
    $variables['same_day'] = $variables['elements']['same_day'] ?? NULL;
    $variables['start_value'] = $variables['elements']['value'] ?? NULL;
    $variables['end_value'] = $variables['elements']['end_value'] ?? NULL;
    $variables['duration'] = $variables['elements']['duration'] ?? NULL;
    $variables['timezone'] = $variables['elements']['timezone'] ?? NULL;
  }

  /**
   * Prepares variables for custom-field-time-range template.
   */
  public function preprocessCustomFieldTimeRange(array &$variables): void {
    $variables['start_value'] = $variables['elements']['value'] ?? NULL;
    $variables['end_value'] = $variables['elements']['end_value'] ?? NULL;
  }

  /**
   * Prepares variables for custom-field-hierarchical-formatter template.
   */
  public function preprocessCustomFieldHierarchicalFormatter(array &$variables): void {
    $terms = [];
    $variables['terms_objects'] = $variables['terms'];

    /** @var \Drupal\Core\Entity\EntityInterface $item */
    foreach ($variables['terms'] as $item) {
      if ($variables['link']) {
        $link = Link::fromTextAndUrl($item->label(), $item->toUrl())->toRenderable();
        $terms[] = $this->renderer->render($link);
      }
      else {
        $terms[] = $item->label();
      }
    }

    if ($variables['wrapper'] !== 'none') {
      $count = 0;
      foreach ($terms as &$term) {
        $count++;
        $term = [
          '#type' => 'html_tag',
          '#tag' => in_array($variables['wrapper'], ['ol', 'ul']) ? 'li' : $variables['wrapper'],
          '#value' => $term,
          '#attributes' => [
            'class' => [
              Html::cleanCssIdentifier('taxonomy-term'),
              Html::cleanCssIdentifier("count $count"),
            ],
          ],
        ];
      }
    }

    unset($variables['link']);
    $variables['terms'] = $terms;
  }

}
