<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\custom_field\Plugin\Field\FieldType\CustomFieldItemListInterface;

/**
 * Provides hooks related to config schemas.
 */
class FormHooks {

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $items = $context['items'];
    if ($items instanceof CustomFieldItemListInterface) {
      $add_more_label = $items->getItemDefinition()->getSetting('add_more_label') ?? '';
      if (isset($element['widget']['add_more']) && !empty(trim($add_more_label))) {
        $element['widget']['add_more']['#value'] = $add_more_label;
      }
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (($field_config = $form_state->get('field_config')) && $field_config->get('field_type') == 'custom') {
      array_unshift(
        $form['actions']['submit']['#submit'],
        'Drupal\custom_field\Plugin\Field\FieldType\CustomItem::submitStorageConfigEditForm'
      );
    }
  }

}
