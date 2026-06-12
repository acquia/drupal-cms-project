<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Set a form field as required.
 */
#[Action(
  id: 'eca_form_field_require',
  label: new TranslatableMarkup('Form field: set as required'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Set a form field as required.'),
  version_introduced: '1.0.0',
)]
class FormFieldRequire extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup {
    return $human_readable ? $this->t('required') : 'required';
  }

}
