<?php

namespace Drupal\Tests\eca\Kernel\Token\Fixtures;

use Drupal\eca\Attribute\Token;
use Drupal\eca\Token\DataProviderInterface;

/**
 * A data provider whose resolved data cannot be serialized.
 *
 * Hashing the resolved value rather than the #[Token] attribute means the
 * hash now runs over arbitrary provider data, which may hold a closure or a
 * resource. Such a value has to degrade into a unique hash that forces
 * re-normalization, exactly as an unserializable plain token value already
 * did, rather than aborting the whole normalization pass.
 */
class UnserializableDataProvider implements DataProviderInterface {

  /**
   * The token key this provider answers for.
   */
  public const string KEY = 'unserializable';

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: self::KEY,
    description: 'A token whose data cannot be serialized.',
  )]
  public function getData(string $key): mixed {
    if ($key !== self::KEY) {
      return NULL;
    }
    // An anonymous object holding a closure. serialize() refuses closures and
    // throws, which is the condition under test.
    return new class() {

      /**
       * A property that serialize() cannot handle.
       *
       * @var \Closure
       */
      public \Closure $callback;

      /**
       * Fills the unserializable property.
       */
      public function __construct() {
        $this->callback = static fn(): string => 'nope';
      }

    };
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(string $key): bool {
    return $key === self::KEY;
  }

}
