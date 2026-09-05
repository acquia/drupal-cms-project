<?php

declare(strict_types=1);

namespace Drupal\easy_encryption_admin\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\easy_encryption\Sodium\Exception\FilesystemPermissionException;
use Drupal\easy_encryption\Sodium\Exception\PrivateKeyMigrationException;
use Drupal\easy_encryption\Sodium\PrivateKeyStorageMigrator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for migrating the private key.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class PrivateKeyStorageMigratorForm extends ConfirmFormBase {

  /**
   * The private key storage migrator.
   *
   * @var \Drupal\easy_encryption\Sodium\PrivateKeyStorageMigrator
   */
  protected PrivateKeyStorageMigrator $migrator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new self();
    $instance->migrator = $container->get(PrivateKeyStorageMigrator::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'easy_encryption_admin_private_key_storage_migrator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to move the private key to the filesystem?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t("This action will move the active private encryption key from being stored in database to a file in a private directory. This is a recommended security improvement.");
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Move key');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('easy_encryption_admin.keys');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->migrator->migrate();
      $this->messenger()->addStatus($this->t('The private key has been successfully migrated to the filesystem.'));
    }
    catch (FilesystemPermissionException $e) {
      $this->messenger()->addError($this->t('The private key could not be migrated due to a filesystem permissions error. Please check the permissions of your private files directory and try again.'));
      $this->getLogger('easy_encryption')->error('Private key migration failed: @error', ['@error' => $e->getMessage()]);
    }
    catch (PrivateKeyMigrationException $e) {
      $this->messenger()->addError($this->t('The private key could not be migrated. Check the logs for more information.'));
      $this->getLogger('easy_encryption')->error('Private key migration failed: @error', ['@error' => $e->getMessage()]);
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
