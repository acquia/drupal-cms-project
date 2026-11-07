<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListDataOperationBase;

/**
 * Action to perform a delete transaction on a list.
 */
#[Action(
  id: 'eca_list_delete_data',
  label: new TranslatableMarkup('List: delete data'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Transaction to delete contained data of a list from the database.'),
  version_introduced: '1.1.0',
)]
class ListDeleteData extends ListDataOperationBase {

  /**
   * {@inheritdoc}
   */
  protected static string $operation = 'delete';

}
