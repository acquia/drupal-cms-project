<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\Hook\KeyEntityHooks;
use Drupal\easy_encryption\KeyManagement\Observers\KeyActivatedObserverInterface;
use Drupal\easy_encryption\KeyManagement\Observers\KeyDeletedObserverInterface;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;
use Drupal\easy_encryption\Sodium\Exception\FilesystemPermissionException;
use Drupal\easy_encryption\Sodium\Exception\PrivateKeyMigrationException;
use Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairNotFoundException;
use Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException;
use Drupal\key\KeyInterface;
use Psr\Log\LoggerInterface;

/**
 * Sodium key pair repository using a hybrid strategy for storing keys.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class SodiumKeyPairRepositoryUsingKeyEntities implements
  SodiumKeyPairReadRepositoryInterface,
  SodiumKeyPairWriteRepositoryInterface,
  KeyActivatedObserverInterface,
  KeyDeletedObserverInterface {

  private const string PRIVATE_KEY_ID_SUFFIX = 'private_key';

  private const string PUBLIC_KEY_ID_SUFFIX = 'public_key';

  /**
   * Suffix appended to labels of Key entities belonging to the active key pair.
   */
  private const string ACTIVE_LABEL_SUFFIX = ' (active)';

  public function __construct(
    private readonly string $appRoot,
    private readonly Settings $settings,
    private readonly KeyRegistryInterface&MutableKeyRegistryInterface $keyRegistry,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly LoggerInterface $logger,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getKeyPairById(string $id): SodiumKeyPair {
    $keyStorage = $this->entityTypeManager->getStorage('key');
    $private_key_entity = $keyStorage->load(self::privateKeyKeyEntityId($id));
    $public_key_entity = $keyStorage->load(self::publicKeyKeyEntityId($id));

    // A keypair is usable for encryption if the public key exists.
    if (!$public_key_entity) {
      throw SodiumKeyPairNotFoundException::forId($id);
    }

    $public_key_value = $public_key_entity->getKeyProvider()
      ->getKeyValue($public_key_entity);
    if (empty($public_key_value)) {
      throw SodiumKeyPairOperationException::publicKeyNotAvailable($id);
    }

    $private_key_value = NULL;
    if ($private_key_entity) {
      $private_key_value = $private_key_entity->getKeyProvider()
        ->getKeyValue($private_key_entity);
      if ($private_key_value === '') {
        $private_key_value = NULL;
      }
    }

    return new SodiumKeyPair(
      $id,
      $this->wrapSodiumFunctionCall('sodium_hex2bin', $public_key_value),
      $private_key_value === NULL ? NULL : $this->wrapSodiumFunctionCall('sodium_hex2bin', $private_key_value),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generateKeyPair(string $id): void {
    // @todo Add transaction rollback support via Checkpoint API.
    $keyStorage = $this->entityTypeManager->getStorage('key');

    // Generate key material.
    $key_pair = $this->wrapSodiumFunctionCall('sodium_crypto_box_keypair');
    $private_key = $this->wrapSodiumFunctionCall('sodium_crypto_box_secretkey', $key_pair);
    $public_key = $this->wrapSodiumFunctionCall('sodium_crypto_box_publickey', $key_pair);

    try {
      $this->persistPrivateKeyEntity($keyStorage, $id, $private_key);
      $this->persistPublicKeyEntity($keyStorage, $id, $public_key);
    }
    catch (\Throwable $e) {
      throw SodiumKeyPairOperationException::generationFailed($e);
    }
    finally {
      try {
        sodium_memzero($private_key);
        sodium_memzero($key_pair);
      }
      catch (\Throwable) {
        // Ignore. sodium_compat may not support memzero.
      }
      unset($private_key, $key_pair);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onKeyActivation(EncryptionKeyId $activeKeyId, ?EncryptionKeyId $previousKeyId): void {
    if ($previousKeyId !== NULL) {
      try {
        $this->setKeyPairActiveLabelState($previousKeyId->value, FALSE);
      }
      catch (EntityStorageException $e) {
        Error::logException($this->logger, $e, sprintf('Failed to remove active label suffix for Key entities belonging to the %s deactivated encryption key.', $previousKeyId->value));
      }
    }

    try {
      $this->setKeyPairActiveLabelState($activeKeyId->value, TRUE);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e, sprintf('Failed to add active label suffix for Key entities belonging to the %s activated encryption key.', $activeKeyId->value));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onKeyDeletion(EncryptionKeyId $keyId): void {
    $this->deleteKeyPair($keyId->value);
  }

  /**
   * {@inheritdoc}
   */
  public function listKeyPairIds(): array {
    $key_id_candidates = [];
    foreach ($this->keyRegistry->listKnownKeyIds()['result'] as $key_id) {
      $key_id_candidates[self::publicKeyKeyEntityId($key_id->value)] = $key_id->value;
      $key_id_candidates[self::privateKeyKeyEntityId($key_id->value)] = $key_id->value;
    }
    if ($key_id_candidates === []) {
      return [];
    }

    $existing_key_entity_ids = $this->entityTypeManager->getStorage('key')
      ->getQuery()
      ->condition('id', array_keys($key_id_candidates), 'IN')
      ->execute();

    return array_unique(array_intersect_key($key_id_candidates, array_flip($existing_key_entity_ids)));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKeyPair(string $id): void {
    // @todo Add transaction rollback support via checkpoint API.
    $keyStorage = $this->entityTypeManager->getStorage('key');
    try {
      $keyStorage->delete(
        $keyStorage->loadMultiple([
          self::privateKeyKeyEntityId($id),
          self::publicKeyKeyEntityId($id),
        ]),
      );
    }
    catch (EntityStorageException $e) {
      throw SodiumKeyPairOperationException::deleteFailed($id, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function upsertPublicKey(string $id, string $publicKeyBin): void {
    if ($publicKeyBin === '') {
      throw new SodiumKeyPairOperationException('Public key must not be empty.');
    }
    $keyStorage = $this->entityTypeManager->getStorage('key');
    try {
      $this->persistPublicKeyEntity($keyStorage, $id, $publicKeyBin);
    }
    catch (\Throwable $e) {
      throw new SodiumKeyPairOperationException(sprintf('Failed to persist public key for key pair "%s".', $id), 0, $e);
    }
    finally {
      try {
        sodium_memzero($publicKeyBin);
      }
      catch (\Throwable) {
        // Ignore. sodium_compat may not support memzero.
      }
      unset($publicKeyBin);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function upsertPrivateKey(string $id, string $privateKeyBin): void {
    if ($privateKeyBin === '') {
      throw new SodiumKeyPairOperationException('Private key must not be empty.');
    }

    $keyStorage = $this->entityTypeManager->getStorage('key');

    try {
      $this->persistPrivateKeyEntity($keyStorage, $id, $privateKeyBin);
    }
    catch (\Throwable $e) {
      throw new SodiumKeyPairOperationException(sprintf('Failed to persist private key for key pair "%s".', $id), 0, $e);
    }
    finally {
      try {
        sodium_memzero($privateKeyBin);
      }
      catch (\Throwable) {
        // Ignore. sodium_compat may not support memzero.
      }
      unset($privateKeyBin);
    }
  }

  /**
   * Persists the private key entity using file storage, falling back to state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  private function persistPrivateKeyEntity(ConfigEntityStorageInterface $keyStorage, string $id, #[\SensitiveParameter] string $private_key): void {
    $private_key_id = self::privateKeyKeyEntityId($id);

    /** @var \Drupal\key\KeyInterface|null $private_key_entity */
    $private_key_entity = $keyStorage->load($private_key_id);

    if (!$private_key_entity instanceof KeyInterface) {
      $private_key_entity = $keyStorage->create([
        'id' => $private_key_id,
        'label' => (string) new TranslatableMarkup('Easy Encryption: Site private key'),
        'description' => (string) new TranslatableMarkup("The site's private key used to decrypt (read) secure credentials."),
        'key_type' => 'encryption',
        'key_type_settings' => [
          // Key module expects key_size in bits. We store the private key as
          // hex, so the string length is (bytes * 2); convert to bits with * 8.
          'key_size' => SODIUM_CRYPTO_BOX_SECRETKEYBYTES * 8 * 2,
        ],
      ]);
      assert($private_key_entity instanceof KeyInterface);
      KeyEntityHooks::activateKeyProviderUpgradeBypass($private_key_entity);
    }

    $private_key_hex = $this->wrapSodiumFunctionCall('sodium_bin2hex', $private_key);

    try {
      $private_key_file_path = $this->writePrivateKeyToFile($private_key_id, $private_key_hex);

      $private_key_entity
        ->set('key_provider', 'file')
        ->set('key_provider_settings', [
          'file_location' => $private_key_file_path,
        ]);

      $this->logger->notice(
        "The site's private key with @id was written to @file. It is recommended to use a more secure storage method, such as an environment variable or external key manager, if possible.",
        ['@id' => $private_key_id, '@file' => $private_key_file_path]
      );
    }
    catch (FilesystemPermissionException $e) {
      $this->logger->warning('Could not write the private key to the filesystem. Falling back to database storage. Error: @error', ['@error' => $e->getMessage()]);

      $this->state->set($private_key_id, $private_key_hex);

      $private_key_entity
        ->set('key_provider', 'state')
        ->set('key_provider_settings', [
          'state_key' => $private_key_id,
        ]);

      $this->logger->warning("The site's private key was stored in the database. This will work, but it is strongly recommended to move the private key to a more secure storage method, such as an environment variable or external key manager.");
    }

    $keyStorage->save($private_key_entity);
  }

  /**
   * Persists the public key entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function persistPublicKeyEntity(ConfigEntityStorageInterface $keyStorage, string $id, string $public_key): void {
    $entity_id = self::publicKeyKeyEntityId($id);

    /** @var \Drupal\key\KeyInterface|null $public_key_entity */
    $public_key_entity = $keyStorage->load($entity_id);

    if (!$public_key_entity instanceof KeyInterface) {
      $public_key_entity = $keyStorage->create([
        'id' => $entity_id,
        'label' => (string) new TranslatableMarkup('Easy Encryption: Site public key'),
        'description' => (string) new TranslatableMarkup("The site's public key used to encrypt (store) sensitive credentials."),
        'key_type' => 'encryption',
        'key_type_settings' => [
          'key_size' => SODIUM_CRYPTO_BOX_PUBLICKEYBYTES * 8 * 2,
        ],
      ]);
      assert($public_key_entity instanceof KeyInterface);
      KeyEntityHooks::activateKeyProviderUpgradeBypass($public_key_entity);
    }

    $public_key_entity->setKeyValue($this->wrapSodiumFunctionCall('sodium_bin2hex', $public_key));
    $keyStorage->save($public_key_entity);
  }

  /**
   * Marks key entities for a key pair as active or inactive by updating labels.
   *
   * This is best-effort per entity: missing entities are silently ignored.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function setKeyPairActiveLabelState(string $keyPairId, bool $active): void {
    $keyStorage = $this->entityTypeManager->getStorage('key');

    $ids = [
      self::publicKeyKeyEntityId($keyPairId),
      self::privateKeyKeyEntityId($keyPairId),
    ];

    /** @var \Drupal\key\KeyInterface[] $entities */
    $entities = $keyStorage->loadMultiple($ids);

    foreach ($entities as $entity) {
      $label_key = $entity->getEntityType()->getKey('label');
      if (!$label_key) {
        continue;
      }

      $label = (string) $entity->get($label_key);
      $new_label = $active
        ? $this->ensureActiveSuffix($label)
        : $this->removeActiveSuffix($label);

      if ($new_label !== $label) {
        $entity->set($label_key, $new_label);
        $entity->save();
      }
    }
  }

  /**
   * Ensures the "(active)" suffix is present.
   */
  private function ensureActiveSuffix(string $label): string {
    if (str_ends_with($label, self::ACTIVE_LABEL_SUFFIX)) {
      return $label;
    }
    return $label . self::ACTIVE_LABEL_SUFFIX;
  }

  /**
   * Removes the "(active)" suffix if present.
   */
  private function removeActiveSuffix(string $label): string {
    if (!str_ends_with($label, self::ACTIVE_LABEL_SUFFIX)) {
      return $label;
    }
    return substr($label, 0, -strlen(self::ACTIVE_LABEL_SUFFIX));
  }

  /**
   * Returns the directory where private keys are stored.
   *
   * @return string
   *   The configured directory path or the default fallback.
   *   Note: This path is not guaranteed to be absolute.
   */
  public function getPrivateKeyDirectory(): string {
    $module_settings = $this->settings::get('easy_encryption', []);
    return ($module_settings['private_key_directory'] ?? dirname($this->appRoot) . DIRECTORY_SEPARATOR . '.easy_encryption');
  }

  /**
   * Wraps a sodium_* function and throws our domain exception when it fails.
   */
  private function wrapSodiumFunctionCall(string $function): mixed {
    assert(is_callable($function), "Function {$function}() is not callable.");
    try {
      $result = $function(...array_slice(func_get_args(), 1));
    }
    catch (\SodiumException $e) {
      throw new SodiumKeyPairOperationException("{$function}() failed with error: {$e->getMessage()}");
    }

    if ($result === FALSE) {
      throw new SodiumKeyPairOperationException("{$function}() failed because it returned false.");
    }

    return $result;
  }

  /**
   * Returns the Key entity ID for the public key of a given encryption key id.
   *
   * @internal
   */
  public static function publicKeyKeyEntityId(string $key_id): string {
    return self::generateKeyEntityId($key_id, self::PUBLIC_KEY_ID_SUFFIX);
  }

  /**
   * Returns the Key entity ID for the private key of a given encryption key id.
   *
   * @internal
   */
  public static function privateKeyKeyEntityId(string $key_id): string {
    return self::generateKeyEntityId($key_id, self::PRIVATE_KEY_ID_SUFFIX);
  }

  /**
   * Generates a Key entity ID.
   *
   * @phpstan-param self::PRIVATE_KEY_ID_SUFFIX|self::PUBLIC_KEY_ID_SUFFIX $type
   */
  private static function generateKeyEntityId(string $prefix, string $type): string {
    return "easy_encrypted__{$prefix}__{$type}";
  }

  /**
   * Calculates plugin dependencies of the active key.
   *
   * @internal
   */
  public function calculateKeyPairPluginDependencies(string $key_id): array {
    $keyStorage = $this->entityTypeManager->getStorage('key');
    /** @var \Drupal\key\KeyInterface|null $private_key_entity */
    $private_key_entity = $keyStorage->load(self::privateKeyKeyEntityId($key_id));
    /** @var \Drupal\key\KeyInterface|null $public_key_entity */
    $public_key_entity = $keyStorage->load(self::publicKeyKeyEntityId($key_id));

    $dependencies = [
      'config' => [
        'easy_encryption.keys',
      ],
    ];
    if ($private_key_entity instanceof KeyInterface) {
      $dependencies['config'][] = $private_key_entity->getConfigDependencyName();
    }
    if ($public_key_entity instanceof KeyInterface) {
      $dependencies['config'][] = $public_key_entity->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * Handles the deletion of a Key entity that might belong to a Sodium pair.
   *
   * @internal
   */
  public function handleEntityDeletion(KeyInterface $key): void {
    $entity_id = (string) $key->id();
    if (!str_starts_with($entity_id, 'easy_encrypted__')) {
      return;
    }

    if (preg_match('/^easy_encrypted__(.+)__(public|private)_key$/', $entity_id, $matches)) {
      [, $key_id_val, $type] = $matches;
      $key_id = EncryptionKeyId::fromNormalized($key_id_val);

      if (!$this->keyRegistry->isKnown($key_id)['result']) {
        return;
      }

      $sibling_id = ($type === 'public')
        ? self::privateKeyKeyEntityId($key_id_val)
        : self::publicKeyKeyEntityId($key_id_val);

      // If the sibling is also gone, the pair is fundamentally broken.
      // We check the storage directly to see if the sibling still exists.
      if (!$this->entityTypeManager->getStorage('key')->load($sibling_id)) {
        $this->keyRegistry->unregister($key_id);

        $this->logger->notice('The encryption key "@id" has been unregistered following the deletion of the "@entity_id" Key entity to preserve data integrity.', [
          '@id' => $key_id->value,
          '@entity_id' => $entity_id,
        ]);
      }
    }
  }

  /**
   * Checks if the private key for a given key ID is stored in the state.
   *
   * @param string $keyId
   *   The key ID.
   *
   * @return bool
   *   TRUE if the key is stored in the state, FALSE otherwise.
   *
   * @internal
   */
  public function isStateStorageUsed(string $keyId): bool {
    $private_key_entity_id = self::privateKeyKeyEntityId($keyId);
    $private_key_entity = $this->entityTypeManager->getStorage('key')->load($private_key_entity_id);

    if (!$private_key_entity instanceof KeyInterface) {
      return FALSE;
    }

    return $private_key_entity->get('key_provider') === 'state';
  }

  /**
   * Migrates the private key from state storage to the filesystem.
   *
   * @param string $keyId
   *   The key ID.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\PrivateKeyMigrationException
   * @throws \Drupal\easy_encryption\Sodium\Exception\FilesystemPermissionException
   *
   * @internal
   */
  public function migrateFromStateToFilesystem(string $keyId): void {
    $private_key_entity_id = self::privateKeyKeyEntityId($keyId);
    /** @var \Drupal\key\KeyInterface|null $private_key_entity */
    $private_key_entity = $this->entityTypeManager->getStorage('key')->load($private_key_entity_id);

    if (!$private_key_entity || $private_key_entity->get('key_provider') !== 'state') {
      throw new \LogicException(sprintf('Private key migration requested for key "%s", but it is not stored in the state.', $keyId));
    }

    try {
      $private_key_value_hex = (string) $private_key_entity->getKeyProvider()->getKeyValue($private_key_entity);
      if ($private_key_value_hex === '') {
        throw new PrivateKeyMigrationException(sprintf('Private key for key "%s" is empty in state storage.', $keyId));
      }

      $private_key_file_path = $this->writePrivateKeyToFile($private_key_entity_id, $private_key_value_hex);

      $private_key_entity
        ->set('key_provider', 'file')
        ->set('key_provider_settings', ['file_location' => $private_key_file_path]);
      $private_key_entity->save();

      $this->state->delete($private_key_entity_id);
    }
    catch (FilesystemPermissionException $e) {
      throw new PrivateKeyMigrationException('Could not migrate the private key to the filesystem.', 0, $e);
    }
    catch (\Exception $e) {
      if ($e instanceof PrivateKeyMigrationException) {
        throw $e;
      }
      throw new PrivateKeyMigrationException(sprintf('An exception occurred during private key migration: %s', $e->getMessage()), 0, $e);
    }
  }

  /**
   * Writes the private key to a file.
   *
   * @param string $key_id
   *   The key ID.
   * @param string $key_hex
   *   The key in hex format.
   *
   * @return string
   *   The path to the file.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\FilesystemPermissionException
   *   If the key could not be written to the file.
   */
  private function writePrivateKeyToFile(string $key_id, string $key_hex): string {
    $private_key_directory = $this->getPrivateKeyDirectory();

    if (!$this->fileSystem->prepareDirectory($private_key_directory, $this->fileSystem::CREATE_DIRECTORY)) {
      throw new FilesystemPermissionException(sprintf('Failed to prepare private key directory at "%s".', $private_key_directory));
    }

    $private_key_file_path = $private_key_directory . DIRECTORY_SEPARATOR . $key_id . '.php';

    error_clear_last();
    // The sprintf is safe because the key is hex encoded.
    if (!@file_put_contents($private_key_file_path, sprintf("<?php return '%s';", $key_hex))) {
      throw new FilesystemPermissionException(sprintf('Failed to write private key to "%s". Last error: %s', $private_key_file_path, error_get_last()['message'] ?? 'Unknown error'));
    }

    if (!$this->fileSystem->chmod($private_key_file_path, 0600)) {
      $this->logger->warning('Could not harden file permissions on the private key at "@file".', ['@file' => $private_key_file_path]);
    }

    return $private_key_file_path;
  }

}
