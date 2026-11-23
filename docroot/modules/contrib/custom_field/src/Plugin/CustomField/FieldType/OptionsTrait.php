<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Trait for list options field settings shared across field types.
 */
trait OptionsTrait {

  use StringTranslationTrait;

  /**
   * Adds options settings to the field settings form.
   *
   * @param array<string, mixed> $element
   *   The form element array to add settings to (passed by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $settings
   *   Current field settings.
   */
  protected function allowedValues(array &$element, FormStateInterface $form_state, array $settings): void {
    $name = $this->getName();
    $form_state_key = $name . '_allowed_values';
    if (!$form_state->get($form_state_key)) {
      $form_state->set($form_state_key, $settings['allowed_values'] ?? []);
    }

    $allowed_values = $form_state->get($form_state_key);
    $wrapper_id = $name . '-allowed-values-wrapper';

    $element['allowed_values'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed values'),
      '#description' => $this->t('Enter the allowed values for <em>select</em> and <em>radios</em> widgets.'),
      '#description_display' => 'before',
      '#element_validate' => [[static::class, 'validateAllowedValues']],
      '#allowed_values' => $allowed_values,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#field_name' => $name,
    ];
    $element['allowed_values']['table'] = [
      '#type' => 'table',
      '#header' => [
        '',
        $this->t('Value'),
        $this->t('Label'),
        $this->t('Delete'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('Widgets that require an options list must have at least one item set.'),
      '#attributes' => [
        'id' => $name . 'allowed-values-order',
        'data-field-list-table' => TRUE,
        'class' => ['allowed-values-table'],
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ];
    if (empty($allowed_values)) {
      $element['allowed_values']['table']['#header'] = [
        $this->t('Items'),
      ];
    }

    foreach ($allowed_values as $delta => $allowed_value) {
      $element['allowed_values']['table'][$delta] = [
        '#weight' => $delta,
      ];
      // Draggable rows have issues on empty value rows.
      if (!empty($allowed_value['key'])) {
        $element['allowed_values']['table'][$delta]['#attributes']['class'] = [
          'draggable',
        ];
      }
      $element['allowed_values']['table'][$delta]['handle'] = [
        '#type' => 'markup',
        '#markup' => '',
      ];
      $element['allowed_values']['table'][$delta]['key'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('Value'),
        '#title_display' => 'invisible',
        '#default_value' => $allowed_value['key'] ?? '',
        '#element_validate' => [[static::class, 'validateAllowedValue']],
        '#required' => TRUE,
        '#size' => 30,
        '#attributes' => [
          'placeholder' => $this->t('Enter a value'),
        ],
      ];
      $element['allowed_values']['table'][$delta]['label'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#default_value' => $allowed_value['label'] ?? '',
        '#required' => TRUE,
        '#attributes' => [
          'placeholder' => $this->t('Enter a label'),
        ],
      ];
      $element['allowed_values']['table'][$delta]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'remove_row_button__' . $name . '__' . $delta,
        '#delta' => $delta,
        '#submit' => [[static::class, 'deleteSubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [static::class, 'deleteAjax'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];
      $element['allowed_values']['table'][$delta]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
        '#title_display' => 'invisible',
        '#delta' => 50,
        '#default_value' => 0,
        '#attributes' => [
          'class' => ['weight'],
        ],
      ];
    }
    $element['allowed_values']['add_more_allowed_values'] = [
      '#type' => 'submit',
      '#name' => 'add_more_allowed_values__' . $name,
      '#value' => $this->t('Add another item'),
      '#attributes' => [
        'class' => ['field-add-more-submit'],
        'data-field-list-button' => TRUE,
      ],
      // Allow users to add another row without requiring existing rows to have
      // values.
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'addMoreAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding a new item...'),
        ],
      ],
    ];
  }

  /**
   * Adds a new option.
   *
   * @param array $form
   *   The form array to add elements to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $user_input = $form_state->getUserInput();
    $parents = array_slice($button['#array_parents'], 0, -2);
    $name = end($parents);
    $form_state_key = $name . '_allowed_values';

    // Get the input values to retain the ordered positioning.
    $input_allowed_values = NestedArray::getValue($user_input, [...$parents, 'allowed_values', 'table']);
    $input_allowed_values[] = [
      'key' => '',
      'label' => '',
      'weight' => 0,
    ];
    $filtered_values = array_map(function ($item) {
      return [
        'key' => $item['key'],
        'label' => $item['label'],
      ];
    }, $input_allowed_values);

    // Reset the user input.
    NestedArray::setValue($user_input, [...$parents, 'allowed_values', 'table'], array_values($input_allowed_values));
    $form_state->setUserInput($user_input);

    // Set the new value to the form state.
    $form_state->set($form_state_key, array_values($filtered_values));

    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    // Set the new value on the table element.
    $form_state->setValueForElement($element, array_values($filtered_values));

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $wrapper_id = $button['#ajax']['wrapper'];

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $element));

    // Set the focus to the first input of the newly added item.
    $children = Element::children($element['table']);
    $last_child = end($children);
    $focus_input = $element['table'][$last_child]['key']['#name'];
    $response->addCommand(new InvokeCommand(':input[name="' . $focus_input . '"]', 'focus'));

    return $response;
  }

  /**
   * Deletes a row/option.
   *
   * @param array $form
   *   The form array to add elements to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function deleteSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $user_input = $form_state->getUserInput();
    $parents = array_slice($button['#array_parents'], 0, -4);
    $name = end($parents);
    $form_state_key = $name . '_allowed_values';
    $remove_key = (int) $button['#delta'];

    // Get the input values to retain the ordered positioning.
    $input_allowed_values = NestedArray::getValue($user_input, [...$parents, 'allowed_values', 'table']) ?? $form_state->get($form_state_key);
    unset($input_allowed_values[$remove_key]);
    $filtered_values = array_map(function ($item) {
      return [
        'key' => $item['key'],
        'label' => $item['label'],
      ];
    }, $input_allowed_values);

    // Set the new value to the form state.
    $form_state->set($form_state_key, array_values($filtered_values));

    // Reset the user input.
    NestedArray::setValue($user_input, [...$parents, 'allowed_values', 'table'], array_values($input_allowed_values));
    $form_state->setUserInput($user_input);

    // Set the new value on the table element.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
    $form_state->setValueForElement($element, array_values($filtered_values));

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for per row delete button.
   */
  public static function deleteAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $button = $form_state->getTriggeringElement();
    $wrapper_id = $button['#ajax']['wrapper'];
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $element));
    $children = Element::children($element['table']);

    // If there are remaining children, set the focus to the first input of the
    // last item.
    if (count($children) > 0) {
      $last_child = end($children);
      $focus_input = $element['table'][$last_child]['key']['#name'] ?? '';
    }
    else {
      $focus_input = $element['add_more_allowed_values']['#name'] ?? '';
    }
    if (!empty($focus_input)) {
      $response->addCommand(new InvokeCommand(':input[name="' . $focus_input . '"]', 'focus'));
    }

    return $response;
  }

  /**
   * Render API callback: Validates the allowed values of an options field.
   *
   * This function is assigned as an #element_validate callback.
   *
   * @param array<string, mixed> $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   *
   * @see \Drupal\Core\Render\Element\FormElementBase::processPattern()
   */
  public static function validateAllowedValues(array $element, FormStateInterface $form_state): void {
    $items = array_filter(array_map(function ($item) use ($element) {
      $current_element = $element['table'][$item];
      $key_has_input = isset($current_element['key']['#value']) && $current_element['key']['#value'] !== '';
      $label_has_input = isset($current_element['label']['#value']) && $current_element['label']['#value'] !== '';
      if (!$key_has_input && !$label_has_input) {
        return NULL;
      }
      return [
        'key' => $current_element['key']['#value'],
        'label' => $current_element['label']['#value'],
      ];
    }, Element::children($element['table'])), function ($item) {
      return $item !== NULL;
    });
    if ($reordered_items = $form_state->getValue([...$element['#parents'], 'table'])) {
      uksort($items, function ($a, $b) use ($reordered_items) {
        $a_weight = $reordered_items[$a]['weight'] ?? 0;
        $b_weight = $reordered_items[$b]['weight'] ?? 0;
        return $a_weight <=> $b_weight;
      });
    }

    // Check that keys are valid for the field type.
    $unique_keys = [];
    foreach ($items as $value) {
      // Make sure each key is unique.
      if (!in_array($value['key'], $unique_keys)) {
        $unique_keys[] = $value['key'];
      }
      else {
        $form_state->setError($element, new TranslatableMarkup('Allowed values list: duplicate key @key.', ['@key' => $value['key']]));
      }
    }

    $form_state->setValueForElement($element, $items);
  }

  /**
   * Render API callback: Validates the allowed key value of an options field.
   *
   * This function is assigned as an #element_validate callback.
   *
   * @param array<string, mixed> $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   */
  public static function validateAllowedValue(array $element, FormStateInterface $form_state): void {
  }

}
