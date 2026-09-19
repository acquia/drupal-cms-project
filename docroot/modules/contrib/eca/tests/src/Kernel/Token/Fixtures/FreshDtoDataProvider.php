<?php

namespace Drupal\Tests\eca\Kernel\Token\Fixtures;

use Drupal\eca\Attribute\Token;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\DataProviderInterface;

/**
 * A data provider that builds a brand new DTO on every resolution.
 *
 * This is the shape of HtmxRequestDataProvider: ::getData() never caches, so
 * two resolutions of the same unchanged request produce two distinct but
 * content-identical DataTransferObject instances. Hashing the resolved value
 * only stays correct here if serialize() treats those two objects as equal,
 * which this fixture exists to exercise.
 *
 * @see \Drupal\eca_htmx\Token\HtmxRequestDataProvider::getData()
 */
class FreshDtoDataProvider implements DataProviderInterface {

  /**
   * The token key this provider answers for.
   */
  public const string KEY = 'fresh_dto';

  /**
   * The values the DTO is built from.
   *
   * @var array
   */
  protected array $values;

  /**
   * Constructs a FreshDtoDataProvider object.
   *
   * @param array $values
   *   The values the DTO is built from.
   */
  public function __construct(array $values) {
    $this->values = $values;
  }

  /**
   * Replaces the values the DTO is built from.
   *
   * @param array $values
   *   The values the DTO is built from now on.
   */
  public function setValues(array $values): void {
    $this->values = $values;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: self::KEY,
    description: 'A token backed by a freshly built DTO.',
    properties: [
      new Token(name: 'alpha', description: 'The alpha value.'),
      new Token(name: 'beta', description: 'The beta value.'),
    ],
  )]
  public function getData(string $key): mixed {
    if ($key !== self::KEY) {
      return NULL;
    }
    return DataTransferObject::create($this->values);
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(string $key): bool {
    return $key === self::KEY;
  }

}
