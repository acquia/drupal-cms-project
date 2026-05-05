<?php

declare(strict_types=1);

namespace Drupal\easy_encryption_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\easy_encryption\KeyTransfer\KeyTransferException;
use Drupal\easy_encryption\KeyTransfer\KeyTransferInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing an Easy Encryption keys.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class ImportEncryptionKeyForm extends FormBase {

  /**
   * The Key transfer service.
   */
  protected KeyTransferInterface $keyTransfer;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the form.
   */
  public function __construct(
    KeyTransferInterface $keyTransfer,
    LoggerInterface $logger,
  ) {
    $this->logger = $logger;
    $this->keyTransfer = $keyTransfer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(KeyTransferInterface::class),
      $container->get('logger.channel.easy_encryption_admin'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'easy_encryption_admin_import_encryption_key';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['package'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exported key text'),
      '#required' => TRUE,
      '#rows' => 12,
    ];

    $form['activate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Activate after import'),
      '#default_value' => FALSE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $package = (string) $form_state->getValue('package');
    $activate = (bool) $form_state->getValue('activate');

    try {
      $result = $this->keyTransfer->importKey($package, $activate);

      $this->messenger()->addStatus($this->t('Imported encryption key "@id".', [
        '@id' => (string) $result['key_id'],
      ]));

      if (!empty($result['activated'])) {
        $this->messenger()->addStatus($this->t('Activated encryption key "@id".', [
          '@id' => (string) $result['key_id'],
        ]));
      }

      $form_state->setRedirect('easy_encryption_admin.keys');
    }
    catch (KeyTransferException $e) {
      $this->messenger()->addError($this->t('Failed to import the encryption key: %message', ['%message' => $e->getMessage()]));
      Error::logException($this->logger, $e);
    }
  }

}
