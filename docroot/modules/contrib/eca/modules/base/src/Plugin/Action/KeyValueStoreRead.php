<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to read value from key value store and store the result as a token.
 */
#[Action(
  id: 'eca_keyvaluestore_read',
  label: new TranslatableMarkup('Key value store: read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Reads a value from the Drupal key value store by the given key. The result is stored in a token.'),
  version_introduced: '2.0.0',
)]
class KeyValueStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): KeyValueStoreInterface {
    return $this->keyValueStoreFactory->get($collection);
  }

}
