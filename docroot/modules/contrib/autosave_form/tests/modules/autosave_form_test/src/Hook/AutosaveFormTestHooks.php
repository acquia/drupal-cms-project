<?php

namespace Drupal\autosave_form_test\Hook;

use Drupal\autosave_form\Form\AutosaveFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for autosave_form_test.
 */
class AutosaveFormTestHooks {

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (isset($form[AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME])) {
      $form[AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME]['#submit'][] = 'autosave_form_test_autosave_submit';
    }
    if (\Drupal::state()->get('disable_html5_validation')) {
      // Disable HTML5 validation.
      $form['#attributes']['novalidate'] = 'novalidate';
    }
  }

}
