<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Adapter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;

/**
 * Encryption key registry backed by the easy_encryption.keys config.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class ConfigKeyRegistry implements KeyRegistryInterface, MutableKeyRegistryInterface {

  /**
   * Config name containing the registry.
   *
   * @internal
   */
  public const CONFIG_NAME = 'easy_encryption.keys';

  /**
   * Constructs the registry.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function listKnownKeyIds(): array {
    $cacheability = new CacheableMetadata();
    $config = $this->getConfig($cacheability);

    $items = $config->get('encryption_keys') ?? [];
    $ids = [];

    foreach ($items as $item) {
      if (!is_array($item) || empty($item['encryption_key_id'])) {
        continue;
      }
      $ids[] = EncryptionKeyId::fromNormalized((string) $item['encryption_key_id']);
    }

    return ['result' => $ids, 'cacheability' => $cacheability];
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveKeyId(): array {
    $cacheability = new CacheableMetadata();
    $config = $this->getConfig($cacheability);

    $active = $config->get('active_encryption_key_id');
    $result = $active ? EncryptionKeyId::fromNormalized((string) $active) : NULL;

    return ['result' => $result, 'cacheability' => $cacheability];
  }

  /**
   * {@inheritdoc}
   */
  public function isKnown(EncryptionKeyId $key_id): array {
    $cacheability = new CacheableMetadata();
    $config = $this->getConfig($cacheability);

    $needle = (string) $key_id;

    foreach (($config->get('encryption_keys') ?? []) as $item) {
      if (is_array($item) && (string) ($item['encryption_key_id'] ?? '') === $needle) {
        return ['result' => TRUE, 'cacheability' => $cacheability];
      }
    }

    return ['result' => FALSE, 'cacheability' => $cacheability];
  }

  /**
   * {@inheritdoc}
   */
  public function register(EncryptionKeyId $key_id): void {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $items = $config->get('encryption_keys') ?? [];
    $needle = (string) $key_id;

    foreach ($items as $item) {
      if (is_array($item) && (string) ($item['encryption_key_id'] ?? '') === $needle) {
        return;
      }
    }

    $items[] = ['encryption_key_id' => $needle];
    try {
      $config->set('encryption_keys', $items)->save();
    }
    catch (\Exception $e) {
      throw KeyRegistryException::registerFailed($key_id->value, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unregister(EncryptionKeyId $key_id): void {
    $config = $this->configFactory->getEditable('easy_encryption.keys');
    $encryption_keys = $config->get('encryption_keys') ?? [];
    $encryption_keys = array_values(array_filter(
      $encryption_keys,
      static fn(array $item) => ($item['encryption_key_id'] ?? NULL) !== $key_id->value
    ));

    try {
      $config
        ->set('encryption_keys', $encryption_keys)
        ->save();
    }
    catch (\Exception $e) {
      throw KeyRegistryException::unregisterFailed($key_id->value, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setActive(EncryptionKeyId $key_id): void {
    $this->register($key_id);
    $this->configFactory
      ->getEditable(self::CONFIG_NAME)
      ->set('active_encryption_key_id', (string) $key_id)
      ->save();
  }

  /**
   * Loads the config and adds it as a cache dependency.
   */
  private function getConfig(CacheableMetadata $cacheability): Config {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $cacheability->addCacheableDependency($config);
    return $config;
  }

}
