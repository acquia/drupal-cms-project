<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Element;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a multiple select_tagify element without a #default_value.
 */
final class SelectTagifyTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tagify_select_tagify_test_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array|null $options
   *   Options for the select_tagify element, or NULL for a flat default set.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $options = NULL): array {
    $form['tags'] = [
      '#type' => 'select_tagify',
      '#title' => 'Tags',
      '#multiple' => TRUE,
      '#options' => $options ?? [
        'a' => 'A',
        'b' => 'B',
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
