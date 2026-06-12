<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Checks whether the current form is submitted.
 */
#[EcaCondition(
  id: 'eca_form_submitted',
  label: new TranslatableMarkup('Form: is submitted'),
  description: new TranslatableMarkup('Checks whether the current form is submitted.'),
  version_introduced: '1.0.0',
)]
class FormSubmitted extends ConditionBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (!($form_state = $this->getCurrentFormState())) {
      return FALSE;
    }
    return $this->negationCheck($form_state->isSubmitted());
  }

}
