<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Set access to a form field.
 */
#[Action(
  id: 'eca_form_field_access',
  label: new TranslatableMarkup('Form field: set access'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Set access to a form field.'),
  version_introduced: '1.0.0',
)]
class FormFieldAccess extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup {
    return $human_readable ? $this->t('access') : 'access';
  }

}
