<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;

/**
 * Checks whether the current form contains a specific form field.
 */
#[EcaCondition(
  id: 'eca_form_field_exists',
  label: new TranslatableMarkup('Form field: exists'),
  description: new TranslatableMarkup('Looks up the current form structure whether a specified field exists.'),
  version_introduced: '1.0.0',
)]
class FormFieldExists extends FormFieldConditionBase {

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (!$this->getCurrentFormState()) {
      return FALSE;
    }
    $field_name = trim((string) $this->tokenService->replace($this->configuration['field_name']));
    if ($field_name === '') {
      throw new \InvalidArgumentException('Cannot use an empty string as field name');
    }
    $this->configuration['field_name'] = $field_name;
    return $this->negationCheck(!empty($this->getTargetElement()));
  }

}
