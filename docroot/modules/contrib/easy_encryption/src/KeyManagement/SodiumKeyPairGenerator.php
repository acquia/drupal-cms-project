<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Utility\Error;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;
use Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException;
use Drupal\easy_encryption\Sodium\SodiumKeyPairWriteRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates encryption key generation and activation.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class SodiumKeyPairGenerator implements KeyGeneratorInterface {

  public function __construct(
    private readonly SodiumKeyPairWriteRepositoryInterface $repository,
    private readonly MutableKeyRegistryInterface $keyRegistry,
    private readonly UuidInterface $uuid,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function generate(?EncryptionKeyId $keyId = NULL): EncryptionKeyId {
    if ($keyId === NULL) {
      $keyId = EncryptionKeyId::fromUserInput($this->uuid->generate());
    }

    try {
      $this->repository->generateKeyPair($keyId->value);
      $this->keyRegistry->register($keyId);
    }
    catch (SodiumKeyPairOperationException | KeyRegistryException $e) {
      Error::logException($this->logger, $e, 'Failed to generate encryption key {id}. @message', ['id' => $keyId->value]);
      throw KeyGeneratorException::generationFailed($keyId->value, $e);
    }

    $this->logger->notice('New encryption key generated with ID {id}.', [
      'id' => $keyId->value,
    ]);
    return $keyId;
  }

}
