<?php

namespace Drupal\Tests\eca\Kernel\Token\Fixtures;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Token\DataProviderInterface;

/**
 * A data provider that counts how often its data gets resolved.
 *
 * Modelled on CurrentUserDataProvider, including the detail that matters for
 * the count: ::hasData() answers by resolving, so every ::getTokenData() of
 * this key costs two resolutions rather than one. That makes the counter a
 * faithful stand-in for the User::load() calls the real provider issues.
 *
 * @see \Drupal\eca\Token\CurrentUserDataProvider
 */
class CountingDataProvider implements DataProviderInterface {

  /**
   * The token key this provider answers for.
   */
  public const string KEY = 'counted';

  /**
   * The number of times the data was resolved.
   *
   * @var int
   */
  public int $resolutions = 0;

  /**
   * The entity to hand out as the token data.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * Constructs a CountingDataProvider object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to hand out as the token data.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Replaces the entity this provider hands out.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to hand out from now on.
   */
  public function setEntity(EntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: self::KEY,
    description: 'A counted token.',
    type: 'user',
  )]
  public function getData(string $key): ?EntityInterface {
    if ($key !== self::KEY) {
      return NULL;
    }
    $this->resolutions++;
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(string $key): bool {
    return $this->getData($key) !== NULL;
  }

}
