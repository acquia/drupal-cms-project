<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Adapter;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyManagement\Port\KeyUsageMapping;
use Drupal\easy_encryption\KeyManagement\Port\KeyUsageProviderInterface;
use Drupal\key\KeyRepositoryInterface;

/**
 * Key usage tracker that tracks encryption key usage in easy_encrypted keys.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyUsageProviderFromEasyEncryptedKeyEntities implements KeyUsageProviderInterface {

  public function __construct(
    private readonly KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getKeyUsageMapping(): array {
    $cacheability = new CacheableMetadata();
    // This tag ensures that if a new Key is added/removed,
    // this result is invalidated.
    $cacheability->addCacheTags(['config:key_list']);

    $results = [];

    foreach ($this->keyRepository->getKeysByProvider('easy_encrypted') as $key) {
      $provider = $key->getKeyProvider();
      if (!$provider instanceof ConfigurableInterface) {
        continue;
      }

      $config = $provider->getConfiguration();
      $encryption_key_id = (string) ($config['encryption_key_id'] ?? '');

      if ($encryption_key_id !== '') {
        $consumer_id = 'key_entity:' . $key->id();

        $results[$consumer_id] = new KeyUsageMapping(
          EncryptionKeyId::fromNormalized($encryption_key_id),
          CacheableMetadata::createFromObject($key)
        );
      }
    }

    return [
      'result' => $results,
      'cacheability' => $cacheability,
    ];
  }

}
