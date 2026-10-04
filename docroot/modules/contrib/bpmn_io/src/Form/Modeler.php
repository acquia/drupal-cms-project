<?php

declare(strict_types=1);

namespace Drupal\bpmn_io\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\modeler_api\Form\EditFormActionButtonsTrait;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Provides a BPMN.iO form into which the modeler can be embedded.
 */
final class Modeler extends FormBase {

  use EditFormActionButtonsTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bpmn_io_modeler';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ModelOwnerInterface $owner = NULL, ?string $id = NULL, bool $readOnly = FALSE): array {
    $form['actions'] = $this->actionButtons($owner, $id, $readOnly);
    return $form;
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
