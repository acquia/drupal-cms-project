<?php

namespace Drupal\custom_field\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a custom Token Browser form element.
 *
 * Usage example:
 * @code
 * $form['token_browser_example'] => [
 *   '#type' => 'token_browser',
 *   '#token_types' => ['node'],
 *   '#recursion_limit' => 4,
 *   '#recursion_limit_max' => 6,
 *   '#global_types' => TRUE,
 *   '#text' => 'Browse available tokens',
 *   '#show_settings' => TRUE,
 * ];
 * @endcode
 *
 * @FormElement("token_browser")
 */
class TokenBrowser extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#token_types' => [],
      '#text' => NULL,
      '#recursion_limit' => 3,
      '#recursion_limit_max' => 6,
      '#global_types' => FALSE,
      '#ajax_wrapper' => 'token-browser-wrapper',
      '#process' => [[static::class, 'processTokenBrowser']],
      '#show_settings' => FALSE,
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Processes the Token Browser element.
   *
   * @param array<string, mixed> $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array<string, mixed>
   *   The processed element.
   */
  public static function processTokenBrowser(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $wrapper_id = $element['#ajax_wrapper'] ?? 'token-browser-wrapper';
    $range = range(1, $element['#recursion_limit_max']);

    $element['recursion_limit'] = [
      '#type' => 'select',
      '#title' => t('Recursion limit'),
      '#description' => t('The depth of the token browser tree.'),
      '#options' => array_combine($range, $range),
      '#default_value' => $element['#recursion_limit'],
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
      ],
      '#access' => $element['#show_settings'],
    ];

    $element['global_types'] = [
      '#type' => 'checkbox',
      '#title' => t('Global types'),
      '#description' => t("Enable 'global' context tokens like [current-user:*] or [site:*]."),
      '#default_value' => $element['#global_types'],
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
      ],
      '#access' => $element['#show_settings'],
    ];

    $element['token_tree_link'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $element['#token_types'],
      '#recursion_limit' => $element['#recursion_limit'],
      '#global_types' => $element['#global_types'],
      '#text' => $element['#text'],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    return $element;
  }

  /**
   * AJAX callback to update the token browser.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $parents = array_slice($trigger['#array_parents'], 0, -1, TRUE);
    $values = $form_state->getValue(array_slice($trigger['#parents'], 0, -1, TRUE));
    $updated_element = NestedArray::getValue($form, [...$parents, 'token_tree_link']);

    // Update Token Browser settings.
    $updated_element['#recursion_limit'] = (int) $values['recursion_limit'];
    $updated_element['#global_types'] = (bool) $values['global_types'];

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));
    // Add a command to close the dialog.
    $response->addCommand(new InvokeCommand('.token-tree-dialog .ui-dialog-content', 'dialog', ['close']));

    return $response;
  }

}
