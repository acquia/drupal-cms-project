<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Core\Field\FieldTypeCategoryManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides hooks related to config schemas.
 */
class GeneralHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      case 'help.page.custom_field':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Create simple, but flexible, multivalue fields without the hassle of entity references.') . '</p>';
        return $output;

      default:
    }

    return '';
  }

  /**
   * Implements hook_field_type_category_info_alter().
   */
  #[Hook('field_type_category_info_alter')]
  public function fieldTypeCategoryInfoAlter(array &$definitions): void {
    // The `custom` field type belongs in the `general` category, so the
    // libraries need to be attached using an alter hook.
    $definitions[FieldTypeCategoryManagerInterface::FALLBACK_CATEGORY]['libraries'][] = 'custom_field/drupal.custom-icon';
  }

  /**
   * Implements hook_sam_allowed_widget_types_alter().
   *
   * Allows custom fields to work with the Simple Add More module.
   *
   * @see https://www.drupal.org/project/sam
   */
  #[Hook('sam_allowed_widget_types_alter')]
  public function samAllowedWidgetTypesAlter(array &$widget_types): void {
    $widget_types[] = 'custom_stacked';
    $widget_types[] = 'custom_flex';
  }

}
