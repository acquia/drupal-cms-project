<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to read value from the expirable key value store.
 *
 * The result is being stored as a token.
 */
#[Action(
  id: 'eca_keyvalueexpirablestore_read',
  label: new TranslatableMarkup('Expirable key value store: read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Reads a value from the Drupal expirable key value store by the given key. The result is stored in a token.'),
  version_introduced: '2.0.0',
)]
class KeyValueExpirableStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): KeyValueStoreExpirableInterface {
    return $this->expirableKeyValueStoreFactory->get($collection);
  }

}
