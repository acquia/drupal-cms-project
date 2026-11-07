<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer;

use Drupal\Core\Utility\Error;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyManagement\KeyActivatorException;
use Drupal\easy_encryption\KeyManagement\KeyActivatorInterface;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;
use Drupal\easy_encryption\KeyTransfer\Port\KeyPackageCodecException;
use Drupal\easy_encryption\KeyTransfer\Port\KeyPackageCodecInterface;
use Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerException;
use Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Default key transfer application service.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyTransfer implements KeyTransferInterface {

  /**
   * Constructs the service.
   *
   * @phpstan-param iterable<\Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerInterface<array>> $payloadHandlers
   */
  public function __construct(
    private readonly KeyPackageCodecInterface $codec,
    private readonly KeyRegistryInterface&MutableKeyRegistryInterface $registry,
    private readonly KeyActivatorInterface $activator,
    private readonly iterable $payloadHandlers,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function exportKey(EncryptionKeyId $keyId): string {
    try {
      $known = $this->registry->isKnown($keyId);
    }
    catch (\Throwable $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Failed to check if encryption key "{id}" is known. @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::exportFailed((string) $keyId, 'Failed to check if key is known.', $e);
    }

    if (empty($known['result'])) {
      $this->logger->warning(
        'Key transfer: Export attempted for unknown encryption key "{id}".',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::exportFailed((string) $keyId, 'Key id is not registered.');
    }

    $handler = $this->getDefaultExportHandler();

    try {
      $payload = $handler->exportPayload($keyId);
    }
    catch (KeyTransferPayloadHandlerException $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Export failed in payload handler for encryption key "{id}". @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::exportFailed((string) $keyId, $e->getMessage(), $e);
    }
    catch (\Throwable $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Unexpected export failure in payload handler for encryption key "{id}". @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::exportFailed((string) $keyId, 'Unexpected export failure in payload handler.', $e);
    }

    try {
      $package = $this->codec->encode([
        'key_id' => (string) $keyId,
        'payload' => $payload,
      ]);
    }
    catch (KeyPackageCodecException $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Failed to encode export package for encryption key "{id}". @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::exportFailed((string) $keyId, $e->getMessage(), $e);
    }

    $this->logger->notice(
      'Key transfer: Exported encryption key "{id}".',
      ['id' => (string) $keyId]
    );

    return $package;
  }

  /**
   * {@inheritdoc}
   */
  public function importKey(string $package, bool $activate = FALSE): array {
    try {
      $data = $this->codec->decode($package);
    }
    catch (KeyPackageCodecException $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Import failed due to invalid package format. @message'
      );
      throw KeyTransferException::invalidPackage($e->getMessage(), $e);
    }

    $id_raw = (string) ($data['key_id'] ?? '');
    $payload = $data['payload'] ?? NULL;

    if ($id_raw === '') {
      $this->logger->warning(
        'Key transfer: Import failed due to missing key_id in package.'
      );
      throw KeyTransferException::invalidPackage('Missing key_id.');
    }
    if (!is_array($payload)) {
      $this->logger->warning(
        'Key transfer: Import failed due to missing payload in package for key "{id}".',
        ['id' => $id_raw]
      );
      throw KeyTransferException::invalidPackage('Missing payload.');
    }

    $keyId = EncryptionKeyId::fromNormalized($id_raw);

    $handler = $this->selectImportHandler($payload);

    try {
      $handler->importPayload($keyId, $payload);
    }
    catch (KeyTransferPayloadHandlerException $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Import failed in payload handler for encryption key "{id}". @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::importFailed($e->getMessage(), $e);
    }
    catch (\Throwable $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Unexpected import failure in payload handler for encryption key "{id}". @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::importFailed('Unexpected import failure in payload handler.', $e);
    }

    try {
      $this->registry->register($keyId);
    }
    catch (\Throwable $e) {
      Error::logException(
        $this->logger,
        $e,
        'Key transfer: Imported key material for encryption key "{id}" but failed to register it. @message',
        ['id' => (string) $keyId]
      );
      throw KeyTransferException::importFailed(
        sprintf('Imported key material but could not register key id "%s".', (string) $keyId),
        $e
      );
    }

    if ($activate) {
      try {
        $this->activator->activate($keyId);
      }
      catch (KeyActivatorException $e) {
        Error::logException(
          $this->logger,
          $e,
          'Key transfer: Imported and registered encryption key "{id}" but activation failed. @message',
          ['id' => (string) $keyId]
        );
        throw KeyTransferException::activationFailed((string) $keyId, $e);
      }
    }

    $this->logger->notice(
      'Key transfer: Imported encryption key "{id}" (activated={activated}).',
      [
        'id' => (string) $keyId,
        'activated' => $activate ? 'true' : 'false',
      ]
    );

    return ['key_id' => $keyId, 'activated' => $activate];
  }

  /**
   * Selects a payload handler for importing a payload.
   *
   * @param array $payload
   *   Decoded payload.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\KeyTransferException
   *   When no handler applies.
   *
   * @return \Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerInterface<array>
   *   The key transfer payload handler for the payload.
   */
  private function selectImportHandler(array $payload): KeyTransferPayloadHandlerInterface {
    foreach ($this->payloadHandlers as $handler) {
      try {
        if ($handler->applies($payload)) {
          return $handler;
        }
      }
      catch (\Throwable) {
        // Ignore broken handlers and allow other handlers to apply.
      }
    }

    throw KeyTransferException::unsupportedPayload('No handler applies.');
  }

  /**
   * Returns the default handler used for exporting.
   *
   * This is intentionally an internal policy for now: the first tagged handler
   * is used. If you later want configurable export formats, this is the one
   * place to change.
   *
   * @return \Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerInterface<array>
   *   The default key transfer payload handler.
   */
  private function getDefaultExportHandler(): KeyTransferPayloadHandlerInterface {
    foreach ($this->payloadHandlers as $handler) {
      return $handler;
    }
    throw new KeyTransferException('No payload handlers are available.');
  }

}
