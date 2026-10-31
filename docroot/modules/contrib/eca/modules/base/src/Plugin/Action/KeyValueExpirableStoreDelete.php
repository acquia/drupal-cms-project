<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to delete value from the expirable key value store.
 */
#[Action(
  id: 'eca_keyvalueexpirablestore_delete',
  label: new TranslatableMarkup('Expirable key value store: delete'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Deletes a value from the Drupal expirable key value store by the given key.'),
  version_introduced: '2.1.5',
)]
class KeyValueExpirableStoreDelete extends KeyValueExpirableStoreRead {

  /**
   * {@inheritdoc}
   */
  protected function deleteMode(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsIfNotExists(): bool {
    return FALSE;
  }

}
