<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Plugin\KeyProvider;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\easy_encryption\Encryption\EncryptedValue;
use Drupal\easy_encryption\Encryption\EncryptorInterface;
use Drupal\easy_encryption\Encryption\EncryptionException;
use Drupal\easy_encryption\KeyManagement\Adapter\ConfigKeyRegistry;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\Sodium\SodiumKeyPairRepositoryUsingKeyEntities;
use Drupal\easy_encryption\Sodium\SodiumKeyPairReadRepositoryInterface;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores key values encrypted at rest using Easy Encryption.
 *
 * This provider encrypts key values before storing them in configuration,
 * allowing sensitive credentials to be safely exported and versioned.
 * Decryption requires the private key to be available in the environment.
 *
 * @KeyProvider(
 *   id = "easy_encrypted",
 *   label = @Translation("Easy Encrypted"),
 *   description = @Translation("Encrypts key values at rest using an asymmetric key pair."),
 *   tags = {
 *     "encryption",
 *   },
 *   key_value = {
 *     "accepted" = TRUE,
 *     "required" = FALSE
 *   }
 * )
 *
 * @internal
 *   This is an internal part of Easy Encryption and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class EasyEncryptedKeyProvider extends KeyProviderBase implements KeyProviderSettableValueInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The encryptor service.
   */
  protected EncryptorInterface $encryptor;

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->encryptor = $container->get(EncryptorInterface::class);
    $instance->logger = $container->get('logger.channel.easy_encryption');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key): string {
    $config = $this->getConfiguration();
    // Let's be lenient on stub keys.
    if ($config === [] || empty($config['value'] ?? '')) {
      return '';
    }

    // A valid configuration on must contain both encrypted value and
    // key pair ID.
    if (empty($config['encryption_key_id'] ?? '')) {
      $this->logger->error('Key entity @id is missing encrypted key pair ID in configuration.', [
        '@id' => $key->id(),
      ]);
      return '';
    }

    try {
      return $this->encryptor->decrypt(
        EncryptedValue::fromHex($config['value'], $config['encryption_key_id'])
      );
    }
    catch (EncryptionException $e) {
      $this->messenger()->addError($this->t('Failed to decrypt the encrypted value. Check logs for more information.'));
      Error::logException($this->logger, $e, 'Failed to decrypt key entity @id with key pair @key_pair_id. @message', [
        '@id' => $key->id(),
        '@key_pair_id' => $config['encryption_key_id'],
      ]);
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setKeyValue(KeyInterface $key, #[\SensitiveParameter] $key_value): bool {
    // An empty value cannot be encrypted, but it can be stored in a key and
    // leads to a stub key.
    if ($key_value === '') {
      return TRUE;
    }

    try {
      $encrypted = $this->encryptor->encrypt($key_value);
    }
    catch (EncryptionException $e) {
      $this->messenger()->addError($this->t('Failed to encrypt value. Check logs for more information.'));
      Error::logException($this->logger, $e, 'Failed to encrypt value for key entity @id. @message', [
        '@id' => $key->id(),
      ]);
      return FALSE;
    }

    $configuration = [
      'value' => $encrypted->getCiphertextHex(),
      'encryption_key_id' => $encrypted->keyId->value,
    ];
    $this->setConfiguration($configuration);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKeyValue(KeyInterface $key): true {
    $this->setConfiguration([]);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = [];

    // Ideally, accessing a port in this layer should not happen, but
    // since we need to expose plugin dependencies for the Key module, we have
    // to make this compromise. If we did that, we also allow accessing the
    // service directly via the container.
    // @phpstan-ignore-next-line
    $key_registry = \Drupal::service(KeyRegistryInterface::class);
    // @phpstan-ignore instanceof.alwaysTrue
    if ($key_registry instanceof ConfigKeyRegistry) {
      $dependencies['config'][] = ConfigKeyRegistry::CONFIG_NAME;
    }
    $active = $key_registry->getActiveKeyId();
    if ($active['result']) {
      // @phpstan-ignore-next-line
      $sodium_repository = \Drupal::service(SodiumKeyPairReadRepositoryInterface::class);
      // @phpstan-ignore instanceof.alwaysTrue
      if ($sodium_repository instanceof SodiumKeyPairRepositoryUsingKeyEntities) {
        $dependencies = $sodium_repository->calculateKeyPairPluginDependencies($active['result']->value);
      }
    }

    return $dependencies;
  }

}
