<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to delete value from the shared temp store.
 */
#[Action(
  id: 'eca_sharedtempstore_delete',
  label: new TranslatableMarkup('Shared temporary store: delete'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Delete a value from the Drupal shared temporary store by the given key.'),
  version_introduced: '2.1.5',
)]
class SharedTempStoreDelete extends SharedTempStoreRead {

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
