<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Drush\Commands;

use Drupal\easy_encryption\Sodium\Exception\FilesystemPermissionException;
use Drupal\easy_encryption\Sodium\Exception\PrivateKeyMigrationException;
use Drupal\easy_encryption\Sodium\PrivateKeyStorageMigrator;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drupal\easy_encryption\KeyManagement\KeyRotationException;
use Drupal\easy_encryption\KeyManagement\KeyRotationOptions;
use Drupal\easy_encryption\KeyManagement\KeyRotatorInterface;

/**
 * Drush commands for Easy Encryption.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
final class EasyEncryptionCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly KeyRotatorInterface $keyRotator,
    private readonly PrivateKeyStorageMigrator $privateKeyStorageMigrator,
  ) {
    parent::__construct();
  }

  /**
   * Rotate the active encryption key & optionally re-encrypt EE keys.
   */
  #[CLI\Command(name: 'easy-encryption:rotate', aliases: ['ee:rotate'])]
  #[CLI\Option(name: 'reencrypt', description: 'Re-encrypt all Key entities using the easy_encrypted provider.')]
  #[CLI\Option(name: 'dry-run', description: 'Do not rotate. Only show what would be re-encrypted.')]
  #[CLI\Option(name: 'no-fail', description: 'Return success even if some credentials fail to re-encrypt (still reported).')]
  #[CLI\Usage(name: 'easy-encryption:rotate --dry-run --reencrypt', description: 'Show how many keys would need re-encryption.')]
  #[CLI\Usage(name: 'easy-encryption:rotate --reencrypt', description: 'Rotate the encryption key and re-encrypt all easy_encrypted keys.')]
  public function rotate(
    array $options = [
      'reencrypt' => FALSE,
      'dry-run' => FALSE,
      'no-fail' => FALSE,
    ],
  ): int {
    $reencrypt = (bool) ($options['reencrypt'] ?? FALSE);
    $dry_run = (bool) ($options['dry-run'] ?? FALSE);
    $no_fail = (bool) ($options['no-fail'] ?? FALSE);

    try {
      if ($dry_run) {
        $plan = $this->keyRotator->plan($reencrypt);

        $this->io()->title(dt('Easy Encryption: key rotation dry-run'));

        $this->io()->definitionList(
          [dt('Active key') => $plan->activeKeyId ?? dt('None configured')],
          [dt('Encrypted keys (total)') => (string) $plan->total],
          [dt('Would be updated') => (string) $plan->toUpdate],
          [dt('Would be skipped') => (string) $plan->toSkip],
        );

        if (!$reencrypt) {
          $this->io()->note(dt('Use --reencrypt to include re-encryption planning counts.'));
        }
        elseif ($plan->activeKeyId === NULL) {
          $this->io()->warning(dt('No active encryption key is configured. Cannot determine which keys need updates.'));
        }

        return self::EXIT_SUCCESS;
      }

      $question = $reencrypt
        ? dt('Rotate the active encryption key and re-encrypt all easy_encrypted keys?')
        : dt('Rotate the active encryption key?');

      if (!$this->io()->confirm($question, FALSE)) {
        $this->io()->writeln(dt('Cancelled.'));
        return self::EXIT_SUCCESS;
      }

      $result = $this->keyRotator->rotate(new KeyRotationOptions(
        reencryptKeys: $reencrypt,
        failOnReencryptErrors: !$no_fail,
      ));

      $this->io()->title(dt('Easy Encryption: rotation completed'));

      $this->io()->definitionList(
        [dt('Old active encryption key') => $result->oldActiveKeyId ?? dt('None configured')],
        [dt('New active encryption key') => $result->newActiveKeyId],
      );

      if ($reencrypt) {
        $this->io()->definitionList(
          [dt('Updated') => (string) $result->updated],
          [dt('Skipped') => (string) $result->skipped],
          [dt('Failed') => (string) $result->failed],
        );
      }

      if ($reencrypt && $result->failed > 0 && $no_fail) {
        $this->io()->warning(dt('Some credentials failed to re-encrypt, but --no-fail was set so the command returned success.'));
      }

      return ($reencrypt && $result->failed > 0 && !$no_fail) ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
    }
    catch (KeyRotationException $e) {
      $this->io()->error(dt($e->getMessage()));

      if ($this->io()->isVerbose() && $e->getPrevious()) {
        $this->io()->writeln(dt('Details: @message', ['@message' => $e->getPrevious()->getMessage()]));
      }

      return self::EXIT_FAILURE;
    }
  }

  /**
   * Migrates the active private key from database to filesystem storage.
   */
  #[CLI\Command(name: 'easy-encryption:migrate-private-key', aliases: ['ee:migrate-private-key'])]
  #[CLI\Option(name: 'check', description: 'Only check if migration is needed, do not perform it.')]
  #[CLI\Usage(name: 'easy-encryption:migrate-private-key --check', description: 'Check if the active private key needs migration.')]
  public function migratePrivateKey(
    array $options = [
      'check' => FALSE,
    ],
  ): int {
    $check_only = (bool) ($options['check'] ?? FALSE);

    $this->io()->title(dt('Easy Encryption: Private Key Storage Migration'));

    try {
      $migration_status = $this->privateKeyStorageMigrator->isMigrationNeeded();
      $is_migration_needed = $migration_status['result'];

      if (!$is_migration_needed) {
        $this->io()->success(dt('No private key migration is needed. The active key is already stored in the filesystem or no active key is configured.'));
        return self::EXIT_SUCCESS;
      }

      if ($check_only) {
        $this->io()->warning(dt('Private key migration is needed. The active key is currently stored in the database.'));
        return self::EXIT_SUCCESS;
      }

      $question = dt('The active private encryption key is currently stored in the database. Do you want to migrate it to the filesystem?');
      if (!$this->io()->confirm($question, FALSE)) {
        $this->io()->writeln(dt('Migration cancelled.'));
        return self::EXIT_SUCCESS;
      }

      $this->io()->note(dt('Attempting to migrate the private key...'));
      $this->privateKeyStorageMigrator->migrate();
      $this->io()->success(dt('Private key successfully migrated from database to filesystem.'));
      return self::EXIT_SUCCESS;
    }
    catch (FilesystemPermissionException $e) {
      $this->io()->error(dt('Private key migration failed due to filesystem permissions: @message', ['@message' => $e->getMessage()]));
      $this->io()->warning(dt('Please ensure the web server has write permissions to the private key storage directory.'));
      return self::EXIT_FAILURE;
    }
    catch (PrivateKeyMigrationException $e) {
      $this->io()->error(dt('Private key migration failed: @message', ['@message' => $e->getMessage()]));
      return self::EXIT_FAILURE;
    }
    catch (\Exception $e) {
      $this->io()->error(dt('An unexpected error occurred during private key migration: @message', ['@message' => $e->getMessage()]));
      if ($this->io()->isVerbose() && $e->getPrevious()) {
        $this->io()->writeln(dt('Details: @message', ['@message' => $e->getPrevious()->getMessage()]));
      }
      return self::EXIT_FAILURE;
    }
  }

}
