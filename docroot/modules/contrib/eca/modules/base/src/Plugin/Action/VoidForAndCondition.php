<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ActionBase;

/**
 * Empty action to chain conditions with AND constraint.
 */
#[Action(
  id: 'eca_void_and_condition',
  label: new TranslatableMarkup('Chain action for AND condition'),
)]
#[EcaAction(
  description: new TranslatableMarkup('This action chains other actions with an explicit AND condition.'),
  version_introduced: '1.0.0',
)]
class VoidForAndCondition extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {}

}
