<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to read value from shared temp store and store the result as a token.
 */
#[Action(
  id: 'eca_sharedtempstore_read',
  label: new TranslatableMarkup('Shared temporary store: read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Reads a value from the Drupal shared temporary store by the given key. The result is stored in a token.'),
  version_introduced: '2.0.0',
)]
class SharedTempStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): SharedTempStore {
    return $this->sharedTempStoreFactory->get($collection);
  }

}
