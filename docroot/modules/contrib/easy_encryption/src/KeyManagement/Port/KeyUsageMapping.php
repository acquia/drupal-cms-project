<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Port;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Represents an encryption key used by a specific consumer with cacheability.
 */
final class KeyUsageMapping implements CacheableDependencyInterface {

  /**
   * Constructs a new object.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId $keyId
   *   The encryption key being used.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|null $cacheability
   *   Optional cacheability metadata for this specific mapping.
   */
  public function __construct(
    public readonly EncryptionKeyId $keyId,
    private readonly ?CacheableDependencyInterface $cacheability = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->cacheability ? $this->cacheability->getCacheContexts() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheability ? $this->cacheability->getCacheTags() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->cacheability ? $this->cacheability->getCacheMaxAge() : CacheBackendInterface::CACHE_PERMANENT;
  }

}
