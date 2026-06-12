<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to write value to the shared temp store.
 */
#[Action(
  id: 'eca_sharedtempstore_write',
  label: new TranslatableMarkup('Shared temporary store: write'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Write a value to the Drupal shared temporary store by the given key.'),
  version_introduced: '2.0.0',
)]
class SharedTempStoreWrite extends SharedTempStoreRead {

  /**
   * {@inheritdoc}
   */
  protected function writeMode(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsIfNotExists(): bool {
    return FALSE;
  }

}
