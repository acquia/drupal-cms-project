<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to write value from the private temp store.
 */
#[Action(
  id: 'eca_privatetempstore_write',
  label: new TranslatableMarkup('Private temporary store: write'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Write a value to the Drupal private temporary store by the given key.'),
  version_introduced: '2.0.0',
)]
class PrivateTempStoreWrite extends PrivateTempStoreRead {

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
