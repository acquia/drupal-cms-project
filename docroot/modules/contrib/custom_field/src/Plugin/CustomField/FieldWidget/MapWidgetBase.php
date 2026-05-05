<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;

/**
 * Base plugin class for map custom field widgets.
 */
class MapWidgetBase extends CustomFieldWidgetBase {

  /**
   * Default new item.
   *
   * @return string|int|array<string|int, mixed>
   *   The default value for new items.
   */
  protected static function newItem(): string|int|array {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $element['#type'] = 'item';

    return $element;
  }

  /**
   * Submit handler for the "add item" button.
   */
  public static function addItem(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $items = $form_state->get($wrapper_id);
    $items[] = static::newItem();
    $form_state->set($wrapper_id, $items);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove item" button.
   */
  public static function removeItem(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $key = $trigger['#attributes']['data-key'];
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $items = $form_state->get($wrapper_id);
    // Only unset if the key exists.
    if (isset($items[$key])) {
      unset($items[$key]);
    }
    $form_state->set($wrapper_id, $items);
    $form_state->setRebuild();
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function actionCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    // Get the triggering element's wrapper ID.
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];

    // Get the current parent array for this widget.
    $parents = $trigger['#array_parents'];
    $length = -1;
    if (isset($trigger['#attributes']['data-key'])) {
      $length = -3;
    }
    $sliced_parents = array_slice($parents, 0, $length, TRUE);

    // Get the updated element from the form structure.
    $updated_element = NestedArray::getValue($form, $sliced_parents)['data'];

    $focus_input = NULL;
    $children = Element::children($updated_element);
    $last_child_key = end($children);
    if ($last_child_key !== FALSE && isset($updated_element[$last_child_key])) {
      $last_child = $updated_element[$last_child_key];
      // For key/value widget.
      if (array_key_exists('key', $last_child)) {
        $focus_input = $last_child['key']['#name'];
      }
      // For text widget.
      elseif (array_key_exists('value', $last_child)) {
        $focus_input = $last_child['value']['#name'];
      }
    }

    // Create an AjaxResponse.
    $response = new AjaxResponse();
    // Add a ReplaceCommand to replace the content inside the widget's wrapper.
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));
    // Set the focus to the newly added item if applicable.
    if (!empty($focus_input)) {
      $response->addCommand(new InvokeCommand('input[name="' . $focus_input . '"]', 'focus'));
    }

    return $response;
  }

}
