<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a multi-value form element.
 *
 * Properties:
 * - #cardinality: the cardinality of this element. Can be a positive number or
 *   MultiValue::CARDINALITY_UNLIMITED to set it as unlimited. The default value
 *   is unlimited.
 * - #add_more_label: the label to use for the "add more" button. The default
 *   value is "Add another item".
 * - #add_empty: Applicable only to fields with unlimited cardinality. If TRUE,
 *   an empty item will be displayed when there are no existing values AND the
 *   field is not required. The default value is FALSE.
 *
 * Use this element as a wrapper for other form elements. They will be repeated
 * based on the cardinality specified, organized under a "delta", similar to
 * field widgets. Deltas are sortable.
 * Example of an element that allows to specify unlimited job title strings:
 * @code
 * $form['job_titles'] = [
 *   '#type' => 'custom_field_multivalue',
 *   '#title' => $this->t('Job titles'),
 *   'title' => [
 *     '#type' => 'textfield',
 *     '#title' => $this->t('Job title'),
 *     '#title_display' => 'invisible',
 *   ],
 * ];
 * @endcode
 *
 * Example of an element with multiple form elements inside. Each "delta" will
 * contain all the children of the main element. This example allows to specify
 * up to three pairs of name/email values:
 * @code
 * $form['contacts'] = [
 *   '#type' => 'custom_field_multivalue',
 *   '#title' => $this->t('Contacts'),
 *   '#cardinality' => 3,
 *   'name' => [
 *     '#type' => 'textfield',
 *     '#title' => $this->t('Name'),
 *   ],
 *   'mail' => [
 *     '#type' => 'email',
 *     '#title' => $this->t('E-mail'),
 *   ],
 * ];
 * @endcode
 *
 * Default values can be set to the multi-value form element. Never set them in
 * child elements as they will be overridden.
 * Pass the default values keyed by their delta:
 * @code
 * $form['contacts'] = [
 *   '#type' => 'custom_field_multivalue',
 *   '#default_value' => [
 *     0 => ['name' => 'Bob', 'mail' => 'bob@example.com'],
 *     1 => ['name' => 'Ted', 'mail' => 'ted@example.com'],
 *   ],
 * ];
 * @endcode
 *
 * If only one child element is present, said child element name can be omitted
 * from the default value array:
 * @code
 * $form['job_titles'] = [
 *   '#type' => 'custom_field_multivalue',
 *   '#title' => $this->t('Job titles'),
 *   'title' => [
 *   ],
 *   '#default_value' => [
 *     'Foo',
 *     'Bar',
 *   ],
 * ];
 * @endcode
 * Note that the values in the form state will always have the full array
 * structure, including the child element name.
 *
 * The element can be marked as required. The required will apply *only* to the
 * first delta. This behavior is consistent with entity fields.
 * How child elements are marked as required depends on their own #required
 * property.
 * Given the multi-value element is marked as required:
 * - if no children is marked as required, all the children of the first delta
 *   will be set as required.
 * - if any children is marked as required, then the required status specified
 *   for the children will be retained for the first delta.
 * For all the deltas after the first, or when the main element is not marked
 * as required, the #required property of the child elements will be set to
 * FALSE.
 *
 * Example of specifying only some elements are required:
 * @code
 * $form['contacts'] = [
 *   '#type' => 'custom_field_multivalue',
 *   '#title' => $this->t('Contacts'),
 *   '#required' => TRUE,
 *   'name' => [
 *     '#type' => 'textfield',
 *     '#title' => $this->t('Name'),
 *     '#required' => TRUE,
 *   ],
 *   'mail' => [
 *     '#type' => 'email',
 *     '#title' => $this->t('E-mail'),
 *   ],
 * ];
 * @endcode
 *
 * If you want to have some children required in all the deltas, use #states
 * to mark the wanted elements as required if one of the other children is
 * filled.
 */
#[FormElement('custom_field_multivalue')]
class MultiValue extends FormElementBase {

  /**
   * Value indicating that an instance of this element accepts unlimited values.
   */
  const CARDINALITY_UNLIMITED = -1;

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#theme' => 'field_multiple_value_form',
      '#cardinality_multiple' => TRUE,
      '#description' => NULL,
      '#cardinality' => self::CARDINALITY_UNLIMITED,
      '#add_more_label' => $this->t('Add another item'),
      '#add_empty' => FALSE,
      '#process' => [
        [$class, 'processMultiValue'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateMultiValue'],
      ],
    ];
  }

  /**
   * Processes a multi-value form element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processMultiValue(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element_name = end($element['#array_parents']);
    $parents = $element['#parents'];
    $cardinality = $element['#cardinality'];

    $id_prefix = implode('-', $parents);
    $wrapper_id = implode('-', [...$parents, 'add-more-wrapper']);

    $element['#tree'] = TRUE;
    $element['#field_name'] = $element_name;

    $element_state = static::getElementState($parents, $element_name, $form_state);
    if ($element_state === NULL) {
      // The initial count is always based on the default value. The default
      // value should always have numeric keys.
      $default_value = $element['#default_value'] ?? NULL;
      $add_empty = $cardinality === self::CARDINALITY_UNLIMITED && !empty($element['#add_empty']);
      if (empty($default_value)) {
        $element_state = ['items_count' => !empty($element['#required']) || $add_empty ? 1 : 0];
      }
      else {
        $default_value = \is_array($default_value) ? $default_value : [];
        $element_state = [
          'items_count' => count($default_value),
        ];
      }

      static::setElementState($parents, $element_name, $form_state, $element_state);
    }

    // Determine the number of elements to display.
    if ($cardinality !== self::CARDINALITY_UNLIMITED) {
      $max = $cardinality;
    }
    else {
      $max = $element_state['items_count'];
    }

    // Extract the elements that will have to be repeated for each delta.
    $children = [];
    foreach (Element::children($element) as $child) {
      $children[$child] = $element[$child];
      unset($element[$child]);
    }

    $value = \is_array($element['#value']) ? $element['#value'] : [];
    // Re-key the elements so that deltas are consecutive.
    $value = \array_values($value);

    for ($i = 0; $i < $max; $i++) {
      $element[$i] = $children;

      if (isset($value[$i])) {
        static::setDefaultValue($element[$i], $value[$i]);
      }

      static::setRequiredProperty($element[$i], $i, $element['#required']);

      $element[$i]['_weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for row @number', ['@number' => $i + 1]),
        '#title_display' => 'invisible',
        '#delta' => $max,
        '#default_value' => $i,
        '#weight' => 100,
      ];

      if ($cardinality === self::CARDINALITY_UNLIMITED) {
        $is_last_required_item = !empty($element['#required']) && $max === 1 && $i === 0;
        $remove_button = [
          '#delta' => $i,
          // @phpstan-ignore argument.type, binaryOp.invalid
          '#name' => strtr($element['#name'], [
            '-' => '_',
            '[' => '_',
            ']' => '',
          ]) . "_{$i}_remove_button",
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#validate' => [],
          '#submit' => [[static::class, 'deleteSubmit']],
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [static::class, 'deleteAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
        ];

        if (!$is_last_required_item) {
          $element[$i]['_actions'] = [
            'delete' => $remove_button,
            '#weight' => 101,
          ];
        }
      }
    }

    if ($cardinality === self::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
      $element['#prefix'] = '<div id="' . $wrapper_id . '">';
      $element['#suffix'] = '</div>';
      $element['add_more'] = [
        '#type' => 'submit',
        '#name' => strtr($id_prefix, '-', '_') . '_add_more',
        '#value' => $element['#add_more_label'],
        '#attributes' => ['class' => ['multivalue-add-more-submit']],
        '#limit_validation_errors' => [$element['#array_parents']],
        '#submit' => [[static::class, 'addMoreSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'addMoreAjax'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];
    }

    return $element;
  }

  /**
   * Submit callback for the "Remove" button.
   *
   * This re-numbers form elements and removes an item.
   *
   * @param array $form
   *   The form array to remove elements from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function deleteSubmit(array &$form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $delta = (int) $button['#delta'];
    $array_parents = \array_slice($button['#array_parents'], 0, -3);
    $element = NestedArray::getValue($form, $array_parents);
    $field_name = $element['#field_name'];

    $user_input = $form_state->getUserInput();
    $field_input = NestedArray::getValue($user_input, $element['#parents'], $exists);

    if ($exists) {
      $non_delta_entries = [];
      $delta_entries = [];

      foreach ($field_input as $key => $input) {
        if (!\is_numeric($key)) {
          $non_delta_entries[$key] = $input;
        }
        elseif ((int) $key !== $delta) {
          $delta_entries[] = $input;
        }
      }

      // Reassign weights sequentially.
      $weight = 0;
      foreach ($delta_entries as &$entry) {
        if (is_array($entry) && isset($entry['_weight'])) {
          $entry['_weight'] = $weight++;
        }
      }
      unset($entry);

      $new_field_input = \array_merge($delta_entries, $non_delta_entries);
      NestedArray::setValue($user_input, $element['#parents'], $new_field_input);
      $form_state->setUserInput($user_input);
    }

    // Decrement the item count for this element.
    $element_state = static::getElementState($element['#parents'], $field_name, $form_state);
    if ($element_state['items_count'] > 0) {
      $element_state['items_count']--;
    }
    static::setElementState($element['#parents'], $field_name, $form_state, $element_state);

    // Re-index nested child element states.
    // When a parent delta is removed, all child multivalue element states
    // stored under higher delta indices must be shifted down by one, otherwise
    // the wrong items_count is read for the shifted deltas on rebuild.
    static::reindexChildElementStates($element['#parents'], $delta, $form_state);

    $form_state->setRebuild();
  }

  /**
   * Re-indexes nested child multivalue element states after a delta is removed.
   *
   * Element state for nested custom_field_multivalue elements is keyed by the
   * full #parents path, which includes the parent delta. When a parent delta is
   * deleted and remaining deltas shift down, the stored state must be moved to
   * match the new delta indices, otherwise the wrong items_count is used on
   * rebuild (e.g. the state from the deleted delta-0 is incorrectly read as
   * belonging to what was delta-1, now promoted to delta-0).
   *
   * @param array $parents
   *   The #parents of the element whose delta was just removed.
   * @param int $removed_delta
   *   The delta that was removed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected static function reindexChildElementStates(array $parents, int $removed_delta, FormStateInterface $form_state): void {
    $storage = &$form_state->getStorage();

    $base_path = \array_merge(['multivalue_form_element_storage', '#parents'], $parents, ['#elements']);
    $elements_storage = NestedArray::getValue($storage, $base_path);

    if (empty($elements_storage)) {
      return;
    }

    $delta_scan_path = \array_merge(['multivalue_form_element_storage', '#parents'], $parents);
    $delta_storage = NestedArray::getValue($storage, $delta_scan_path);

    if (!is_array($delta_storage)) {
      return;
    }

    $deltas_to_shift = [];
    foreach ($delta_storage as $key => $value) {
      if (\is_numeric($key) && (int) $key > $removed_delta) {
        $deltas_to_shift[] = (int) $key;
      }
    }

    sort($deltas_to_shift);

    $ref = &NestedArray::getValue($storage, $delta_scan_path);

    foreach ($deltas_to_shift as $old_delta) {
      $ref[$old_delta - 1] = $ref[$old_delta];
      unset($ref[$old_delta]);
    }

    unset($ref[$removed_delta]);
  }

  /**
   * Ajax callback for the "Remove" button.
   *
   * This returns the new widget element content to replace
   * the previous content made obsolete by the form submission.
   *
   * @param array $form
   *   The form array to remove elements from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The new widget element content.
   */
  public static function deleteAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $button = $form_state->getTriggeringElement();

    // Go 3 levels up in the form, to the widget container.
    $element = NestedArray::getValue($form, \array_slice($button['#array_parents'], 0, -3));
    $wrapper_id = $button['#ajax']['wrapper'];
    $element_state = static::getElementState($element['#parents'], $element['#field_name'], $form_state);
    $previous_delta = $element_state['items_count'] - 1;

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $element));
    if (isset($element[$previous_delta])) {
      $focus_name = static::findFirstFocusableInputName($element[$previous_delta]);
      if (!empty($focus_name)) {
        // Set the focus to the first input of last remaining element.
        $response->addCommand(new InvokeCommand('input[name="' . $focus_name . '"]', 'focus'));
      }
    }

    return $response;
  }

  /**
   * Validates a multi-value form element.
   *
   * Used to clean and sort the submitted values in the form state.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateMultiValue(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input_exists = FALSE;
    $form_state_values = &$form_state->getValues();
    $values = NestedArray::getValue($form_state_values, $element['#parents'], $input_exists);

    if (!$input_exists) {
      return;
    }

    // Remove the 'value' of the 'add more' button.
    unset($values['add_more']);

    // Sort the values based on the weight.
    usort($values, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });

    foreach ($values as $delta => &$delta_values) {
      // Remove the weight and action element values from the submitted data.
      unset($delta_values['_weight'], $delta_values['_actions']);

      // Determine if all the elements of this delta are empty.
      // If all the elements are empty, drop this delta.
      if (static::isDeltaEmpty($delta_values)) {
        unset($values[$delta]);
      }
    }

    // Re-key the elements so that deltas are consecutive.
    $values = \array_values($values);

    // Set the value back to the form state.
    $form_state->setValueForElement($element, $values);
  }

  /**
   * Recursively determines if a delta's values are all empty.
   *
   * Handles arbitrary nesting depth, including nested multivalue elements
   * whose values are structured arrays rather than scalar strings.
   *
   * @param mixed $value
   *   The value to check. May be a scalar, or a nested array of values.
   *
   * @return bool
   *   TRUE if the value is considered empty, FALSE otherwise.
   */
  protected static function isDeltaEmpty(mixed $value): bool {
    if (\is_array($value)) {
      foreach ($value as $item) {
        if (!static::isDeltaEmpty($item)) {
          return FALSE;
        }
      }
      return TRUE;
    }

    return $value === NULL || $value === '' || $value === 0 || $value === FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      return $input;
    }

    $value = [];
    $element += ['#default_value' => []];

    $children_keys = Element::children($element);
    $first_child = reset($children_keys);
    $children_count = count($children_keys);

    foreach ($element['#default_value'] as $delta => $default_value) {
      // Enforce numeric deltas.
      if (!\is_numeric($delta)) {
        continue;
      }

      // Allow omitting the child element name when one single child exists and
      // the values are simple literals. This allows passing
      // [0 => 'value 1', 1 => 'value 2'] instead of
      // [0 => ['element_name' => 'value 1', 1 => ['element_name' => ...]].
      if ($children_count === 1 && !\is_array($default_value)) {
        $value[(int) $delta] = [$first_child => $default_value];
      }
      else {
        $value[(int) $delta] = $default_value;
      }
    }

    return $value;
  }

  /**
   * Handles the "Add another item" button AJAX request.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\Core\Field\WidgetBase::addMoreSubmit()
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets' container.
    $element = NestedArray::getValue($form, \array_slice($button['#array_parents'], 0, -1));
    $element_name = $element['#field_name'];
    $parents = $element['#parents'];

    // Increment the item count.
    $element_state = static::getElementState($parents, $element_name, $form_state);
    $element_state['items_count']++;
    static::setElementState($parents, $element_name, $form_state, $element_state);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|null
   *   The element.
   *
   * @see \Drupal\Core\Field\WidgetBase::addMoreAjax()
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): ?AjaxResponse {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widget container.
    $element = NestedArray::getValue($form, \array_slice($button['#array_parents'], 0, -1));

    // Ensure the widget allows adding additional items.
    if ($element['#cardinality'] != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return NULL;
    }

    $wrapper_id = $button['#ajax']['wrapper'];
    $element_state = static::getElementState($element['#parents'], $element['#field_name'], $form_state);
    $new_delta = $element_state['items_count'] - 1;

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $element));
    if (isset($element[$new_delta])) {
      $focus_name = static::findFirstFocusableInputName($element[$new_delta]);
      if (!empty($focus_name)) {
        // Set the focus to the first input of the new element.
        $response->addCommand(new InvokeCommand('input[name="' . $focus_name . '"]', 'focus'));
      }
    }

    return $response;
  }

  /**
   * Returns the name of the first focusable input control in the given element.
   *
   * @param array<string, mixed> $elements
   *   The elements array.
   *
   * @return string|null
   *   The name of the first focusable input control, or NULL if none is found.
   */
  protected static function findFirstFocusableInputName(array $elements): ?string {
    foreach (Element::children($elements) as $key) {
      if ($key === '_weight' || $key === '_actions') {
        continue;
      }

      $child = $elements[$key];
      $type = $child['#type'] ?? NULL;

      if ($type === 'submit' || $type === 'hidden' || $type === 'value') {
        continue;
      }

      // If #name is set, this element renders a focusable input control.
      if (!empty($child['#name'])) {
        return $child['#name'];
      }

      // If the child has a first delta, recurse into that.
      if (isset($child[0]) && \is_array($child[0])) {
        $found = static::findFirstFocusableInputName($child[0]);
        if ($found !== NULL) {
          return $found;
        }
      }

      // No #name means it's a container of some kind — recurse into it.
      $found = static::findFirstFocusableInputName($child);
      if ($found !== NULL) {
        return $found;
      }
    }

    return NULL;
  }

  /**
   * Sets the default value for the child elements.
   *
   * @param array $elements
   *   The elements array.
   * @param array $value
   *   An array of values, keyed by the children element name.
   */
  public static function setDefaultValue(array &$elements, array $value): void {
    foreach (Element::children($elements) as $child) {
      $element = &$elements[$child];
      $type = $element['#type'] ?? NULL;

      // If this child has no corresponding value key but is a non-input
      // container, recurse into it passing the full $value array so its
      // children can be matched against it.
      if (!isset($value[$child])) {
        $is_input = $element['#input'] ?? FALSE;
        if (!$is_input && !empty(Element::children($element))) {
          static::setDefaultValue($element, $value);
        }
        // Ensure array-value elements like checkboxes always have an empty
        // array default to avoid PHP 8.2 null offset deprecations in their
        // value callbacks when no value is present.
        elseif (!empty($element['#multiple']) || \in_array($type, [
          'checkboxes',
          'tableselect',
        ])) {
          $element['#default_value'] = $element['#default_value'] ?? [];
        }
        continue;
      }
      $default_value = $value[$child];

      if (\is_array($default_value)) {
        $child_count = count(Element::children($element));
        if (empty($child_count) || $type === 'custom_field_multivalue') {
          $element['#default_value'] = $default_value;
        }
        else {
          static::setDefaultValue($element, $default_value);
        }
      }
      else {
        $element['#default_value'] = $default_value;
      }
    }
  }

  /**
   * Sets the required property for the delta being processed.
   *
   * @param array $elements
   *   The array containing the child elements.
   * @param int $delta
   *   The delta currently being processed.
   * @param bool $required
   *   If the main element is required or not.
   */
  protected static function setRequiredProperty(array &$elements, int $delta, bool $required): void {
    if ($delta === 0 && $required) {
      // If any of the children is set as required, the first delta is already
      // set correctly.
      foreach ($elements as $element) {
        if (isset($element['#required']) && $element['#required'] === TRUE) {
          return;
        }
      }

      // Set all children as required otherwise.
      foreach ($elements as &$element) {
        $element['#required'] = TRUE;
      }

      return;
    }

    // For every other delta or when the main element is marked as not required,
    // none of the children should be required either.
    foreach ($elements as &$element) {
      $element['#required'] = FALSE;
    }
  }

  /**
   * Retrieves processing information about the element from $form_state.
   *
   * This method is static so that it can be used in static Form API callbacks.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The field name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|null
   *   An array with the following key/value pairs:
   *   - items_count: The number of sub-elements to display for the element.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetState()
   */
  public static function getElementState(array $parents, string $element_name, FormStateInterface $form_state): ?array {
    $storage = $form_state->getStorage();
    return NestedArray::getValue($storage, static::getElementStateParents($parents, $element_name));
  }

  /**
   * Stores processing information about the element in $form_state.
   *
   * This method is static so that it can be used in static Form API #callbacks.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The element name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_state
   *   The array of data to store. See getElementState() for the structure and
   *   content of the array.
   *
   * @see \Drupal\Core\Field\WidgetBase::setWidgetState()
   */
  public static function setElementState(array $parents, string $element_name, FormStateInterface $form_state, array $field_state): void {
    $storage = &$form_state->getStorage();
    NestedArray::setValue($storage, static::getElementStateParents($parents, $element_name), $field_state);
  }

  /**
   * Returns the location of processing information within $form_state.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The element name.
   *
   * @return array
   *   The location of processing information within $form_state.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetStateParents()
   */
  protected static function getElementStateParents(array $parents, string $element_name): array {
    // phpcs:disable
    // Element processing data is placed at
    // $form_state->get(['multivalue_form_element_storage', '#parents', ...$parents..., '#elements', $element_name]),
    // to avoid clashes between field names and $parents parts.
    // phpcs:enable
    return array_merge(['multivalue_form_element_storage', '#parents'],
      $parents,
      ['#elements', $element_name],
    );
  }

}
