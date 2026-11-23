<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to read value from private temp store and store the result as a token.
 */
#[Action(
  id: 'eca_privatetempstore_read',
  label: new TranslatableMarkup('Private temporary store: read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Reads a value from the Drupal private temporary store by the given key. The result is stored in a token.'),
  version_introduced: '2.0.0',
)]
class PrivateTempStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): PrivateTempStore {
    return $this->privateTempStoreFactory->get($collection);
  }

}
