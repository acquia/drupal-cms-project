<?php

declare(strict_types=1);

namespace Drupal\ui_icons_ckeditor5\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\filter\FilterFormatInterface;

/**
 * Provides a UI Icons Ckeditor5 form.
 */
final class IconDialog extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_ckeditor5_icon_dialog';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\filter\FilterFormatInterface|null $filter_format
   *   The text editor format to which this dialog corresponds.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormatInterface $filter_format = NULL): array {
    if (NULL === $filter_format) {
      return [];
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#prefix'] = '<div id="editor-icon-dialog-form">';
    $form['#suffix'] = '</div>';

    $settings = $filter_format->filters('icon_embed')->getConfiguration()['settings'] ?? [];
    $allowed_icon_pack = $settings['allowed_icon_pack'] ?? [];
    $result_format = $settings['result_format'] ?? 'list';
    $max_result = $settings['max_result'] ?? 24;

    $form['icon'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Icon Name'),
      '#size' => 35,
      '#required' => TRUE,
      '#allowed_icon_pack' => $allowed_icon_pack,
      '#show_settings' => TRUE,
      '#result_format' => $result_format,
      '#max_result' => $max_result,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
      // Prevent this hidden element from being tabbable.
      '#attributes' => [
        'tabindex' => -1,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#editor-icon-dialog-form', $form));
      return $response;
    }

    $values = [
      'settings' => [
        'icon' => NULL,
      ],
    ];

    $value = $form_state->getValue('icon');
    $icon = $value['icon'] ?? NULL;

    if ($icon instanceof IconDefinitionInterface) {
      $values['settings']['icon'] = $icon->getId();
      $values['settings']['icon_settings'] = $value['settings'][$icon->getPackId()] ?? [];
    }

    $response->addCommand(new EditorDialogSave($values));
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

}
