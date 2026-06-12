<?php

declare(strict_types=1);

namespace Drupal\modeler_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form wrapper for ajax config forms for components.
 */
final class Wrapper extends FormBase {

  use EditFormActionButtonsTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'modeler_api_wrapper';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $embedded = NULL): array {
    return $embedded ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
