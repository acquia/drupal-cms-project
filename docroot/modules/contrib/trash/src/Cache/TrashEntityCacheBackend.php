<?php

declare(strict_types=1);

namespace Drupal\trash\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates the entity cache backend to filter out deleted entities.
 */
class TrashEntityCacheBackend implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  use TrashCacheBackendTrait;

  public function __construct(
    #[AutowireDecorated]
    protected CacheBackendInterface $inner,
  ) {}

}
