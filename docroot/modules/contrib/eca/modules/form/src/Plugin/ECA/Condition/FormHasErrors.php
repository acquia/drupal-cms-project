<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Checks whether the current form state has any errors.
 */
#[EcaCondition(
  id: 'eca_form_has_errors',
  label: new TranslatableMarkup('Form: has any errors'),
  description: new TranslatableMarkup('Checks whether the current form state has any errors.'),
  version_introduced: '1.0.0',
)]
class FormHasErrors extends ConditionBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (!($form_state = $this->getCurrentFormState())) {
      return FALSE;
    }
    return $this->negationCheck($form_state::hasAnyErrors());
  }

}
