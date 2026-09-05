<?php

namespace Drupal\linkit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\linkit\Annotation\Matcher as MatcherAnnotation;
use Drupal\linkit\Attribute\Matcher as MatcherAttribute;

/**
 * Manages matchers.
 */
class MatcherManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Linkit/Matcher',
      $namespaces,
      $module_handler,
      MatcherInterface::class,
      MatcherAttribute::class,
      MatcherAnnotation::class
    );

    $this->alterInfo('linkit_matcher');
    $this->setCacheBackend($cache_backend, 'linkit_matchers');
  }

}
