<?php

namespace Drupal\eca_misc\Plugin\ECA\Condition;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;

/**
 * Condition plugin to determine if the current request is an AJAX request.
 */
#[EcaCondition(
  id: 'eca_is_ajax',
  label: new TranslatableMarkup('Is Ajax'),
  description: new TranslatableMarkup('Determines if the current request is an AJAX request.'),
  version_introduced: '2.1.8',
)]
class IsAjax extends ConditionBase {

  use AjaxHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $result = $this->isAjax();
    return $this->negationCheck($result);
  }

}
