<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\easy_encryption\KeyManagement\KeyActivatorException;
use Drupal\easy_encryption\KeyManagement\KeyActivatorInterface;
use Drupal\easy_encryption\KeyManagement\KeyGeneratorException;
use Drupal\easy_encryption\KeyManagement\KeyGeneratorInterface;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ensures Easy Encryption is set up for recipe-based installs.
 *
 * Recipes enable modules without invoking hook_install(), so this config action
 * ensures that an initial key pair exists and is active.
 *
 * The action is idempotent: if an active key pair is already configured, it
 * performs no changes.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[ConfigAction(
  id: 'ensureEasyEncryptionSetup',
  admin_label: new TranslatableMarkup('Ensure Easy Encryption is set up'),
  entity_types: ['*'],
)]
final class EnsureEasyEncryptionSetup implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly KeyGeneratorInterface $keyGenerator,
    private readonly KeyActivatorInterface $keyActivator,
    private readonly KeyRegistryInterface $keyRegistry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $container->get(KeyGeneratorInterface::class),
      $container->get(KeyActivatorInterface::class),
      $container->get(KeyRegistryInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // If an active key pair already exists, do nothing.
    $active = $this->keyRegistry->getActiveKeyId();
    if ($active['result'] !== NULL) {
      return;
    }

    try {
      $keypair_id = $this->keyGenerator->generate();
    }
    catch (KeyGeneratorException $e) {
      throw new ConfigActionException(
        'Easy Encryption setup failed: unable to generate new encryption key.', 0, $e
      );
    }

    try {
      $this->keyActivator->activate($keypair_id);
    }
    catch (KeyActivatorException $e) {
      throw new ConfigActionException(
        sprintf('Easy Encryption setup failed: unable to activate newly generated "%s" encryption key.', $keypair_id->value), 0, $e
      );
    }
  }

}
