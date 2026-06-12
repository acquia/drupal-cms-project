<?php

declare(strict_types=1);

namespace Drupal\trash\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates the entity memory cache backend to filter out deleted entities.
 */
class TrashEntityMemoryCacheBackend implements MemoryCacheInterface {

  use TrashCacheBackendTrait;

  public function __construct(
    #[AutowireDecorated]
    protected CacheBackendInterface $inner,
  ) {}

}
