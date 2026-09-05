<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ui_icons\Element\IconAutocomplete;

/**
 * Provides a form element to select an icon with a fancy picker.
 *
 * Properties:
 * - #default_value: (string) Icon value as pack_id:icon_id.
 * - #show_settings: (bool) Enable extractor settings, default FALSE.
 * - #default_settings: (array) Settings for the extractor settings.
 * - #settings_title: (string) Extractor settings details title.
 * - #allowed_icon_pack: (array) Icon pack to limit the selection.
 * - #return_id: (bool) Form return icon id instead of icon object as default.
 *
 * Some base properties from FormElementBase.
 * - #description: (string) Help or description text for the input element.
 * - #placeholder: (string) Placeholder text for the input, default to
 *   'Click to select an Icon'.
 * - #required: (bool) Whether or not input is required on the element.
 * - #size: (int): Textfield size, default 55.
 *
 * Global properties applied to the parent element:
 * - #attributes': (array) Attributes to the global element.
 *
 * @see web/core/lib/Drupal/Core/Render/Element/FormElementBase.php
 *
 * Usage example:
 * @code
 * $form['icon'] = [
 *   '#type' => 'icon_picker',
 *   '#title' => $this->t('Select icon'),
 *   '#default_value' => 'my_icon_pack:my_default_icon',
 *   '#allowed_icon_pack' => [
 *     'my_icon_pack,
 *     'other_icon_pack',
 *   ],
 *   '#show_settings' => TRUE,
 * ];
 * @endcode
 */
#[FormElement('icon_picker')]
class IconPicker extends IconAutocomplete {

  /**
   * Callback for creating form sub element icon_id.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element with icon_id element.
   */
  public static function processIcon(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element = parent::processIcon($element, $form_state, $complete_form);

    $element['icon_id']['#placeholder'] = $element['#placeholder'] ?? '';
    $element['icon_id']['#description'] = new TranslatableMarkup('Click to select an Icon.');
    $element['icon_id']['#attached'] = [
      'library' => [
        'ui_icons_picker/picker',
      ],
    ];

    $element['icon_id']['#attributes'] = [
      'data-dialog-url' => Url::fromRoute('ui_icons_picker.ui')->toString(),
      'class' => [
        'form-icon-dialog',
      ],
    ];

    if (!empty($element['#allowed_icon_pack'])) {
      $element['icon_id']['#attributes']['data-allowed-icon-pack'] = implode('+', $element['#allowed_icon_pack']);
    }

    unset($element['icon_id']['#autocomplete_route_name']);
    unset($element['icon_id']['#autocomplete_query_parameters']);

    return $element;
  }

}
