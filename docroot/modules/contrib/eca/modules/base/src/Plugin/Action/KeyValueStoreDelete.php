<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to delete value from the key value store.
 */
#[Action(
  id: 'eca_keyvaluestore_delete',
  label: new TranslatableMarkup('Key value store: delete'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Delete a value from the Drupal key value store by the given key.'),
  version_introduced: '2.1.5',
)]
class KeyValueStoreDelete extends KeyValueStoreRead {

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
