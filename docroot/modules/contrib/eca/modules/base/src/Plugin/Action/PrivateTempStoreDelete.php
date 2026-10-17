<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to delete value from the private temp store.
 */
#[Action(
  id: 'eca_privatetempstore_delete',
  label: new TranslatableMarkup('Private temporary store: delete'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Delete a value from the Drupal private temporary store by the given key.'),
  version_introduced: '2.1.5',
)]
class PrivateTempStoreDelete extends PrivateTempStoreRead {

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
