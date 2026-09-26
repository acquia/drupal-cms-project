<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListDataOperationBase;

/**
 * Action to perform a save transaction on a list.
 */
#[Action(
  id: 'eca_list_save_data',
  label: new TranslatableMarkup('List: save data'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Transaction to save contained data of a list into the database.'),
  version_introduced: '1.1.0',
)]
class ListSaveData extends ListDataOperationBase {

  /**
   * {@inheritdoc}
   */
  protected static string $operation = 'save';

}
