<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormValidatorInterface;

/**
 * Provides validation of form submissions with AutosaveForm enabled.
 */
class AutosaveFormValidator implements FormValidatorInterface {

  use AutosaveButtonClickedTrait;

  /**
   * The form validator service.
   *
   * @var \Drupal\Core\Form\FormValidatorInterface
   */
  protected $formValidator;

  /**
   * Constructs a AutosaveFormValidator object.
   *
   * @param \Drupal\Core\Form\FormValidatorInterface $form_validator
   *   The form validator service.
   */
  public function __construct(FormValidatorInterface $form_validator) {
    $this->formValidator = $form_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function executeValidateHandlers(&$form, FormStateInterface &$form_state) {
    $this->formValidator->executeValidateHandlers($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm($form_id, &$form, FormStateInterface &$form_state) {
    $autosave_submission = $this->isAutosaveTriggered($form_state);

    if ($autosave_submission) {
      // On subsequent autosaving we don't need to execute the form validation
      // as we are not going to build the intermediate entity. However it might
      // happen that between here and the autosave submission handler the
      // autosaved state has been purged and therefore we have to check
      // explicitly for that there instead of building the intermediate entity.
      $form_state->setTemporaryValue('autosave_form_form_validation_skipped', TRUE);
    }
    else {
      // We have to execute the validation in the case of autosave submission
      // for the very first time as in this case we'll build the intermediate
      // entity for comparison and some input values are being prepared in the
      // validate functions. This is the case with e.g. autocomplete for entity
      // references.
      $this->formValidator->validateForm($form_id, $form, $form_state);
    }

    // In order for the autosave submit callback to be executed we have to
    // clear the errors caused from the validation, otherwise no submit
    // callbacks will be executed.
    if ($autosave_submission && $form_state::hasAnyErrors()) {
      $form_state->clearErrors();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setInvalidTokenError(FormStateInterface $form_state) {
    $this->formValidator->setInvalidTokenError($form_state);
  }

}
