<?php

namespace Drupal\checklistapi\Storage;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides config-based checklist progress storage.
 */
class ConfigStorage extends StorageBase {

  /**
   * The configuration key for saved progress.
   */
  const CONFIG_KEY = 'progress';

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config|null
   */
  private $config;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Constructs a class instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->configFactory = $config_factory;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public function getSavedProgress() {
    return $this->getConfig()->get(self::CONFIG_KEY);
  }

  /**
   * {@inheritdoc}
   */
  public function setSavedProgress(array $progress) {
    $this->getConfig()->set(self::CONFIG_KEY, $progress)->save();
    $this->cacheTagsInvalidator->invalidateTags($this->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSavedProgress() {
    $this->getConfig()->delete();
    $this->cacheTagsInvalidator->invalidateTags($this->getCacheTags());
  }

  /**
   * Gets the config object.
   *
   * @return \Drupal\Core\Config\Config
   *   Returns the config object.
   */
  private function getConfig() {
    if (empty($this->config)) {
      $this->config = $this->configFactory
        ->getEditable("checklistapi.progress.{$this->getChecklistId()}");
    }
    return $this->config;
  }

}
