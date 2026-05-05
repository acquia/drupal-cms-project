<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\Plugin\ConfigAction;

use Drupal\ai_provider_amazeeio\TrialAccess\ProgressReporterFactoryInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningException;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ensures that Amazee AI access is configured.
 *
 * Ensuring access means that the config action may provision an anonymous
 * Amazee AI trial account if it is not configured yet.
 *
 * This is a lightweight wrapper around the Trial Access provisioner, which can
 * not be exposed as a config action directly.
 *
 * @internal
 */
#[ConfigAction(
  id: 'ensureAmazeeAiAccess',
  admin_label: new TranslatableMarkup('Ensure Amazee AI access'),
  entity_types: ['*'],
)]
final class EnsureAmazeeAiAccess implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly TrialAccountProvisionerFactoryInterface $trialAccountProvisionerFactory,
    private readonly ProgressReporterFactoryInterface $progressReporterFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $container->get(TrialAccountProvisionerFactoryInterface::class),
      $container->get(ProgressReporterFactoryInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    try {
      $this->trialAccountProvisionerFactory->create($this->progressReporterFactory->forCurrentRuntime())->provision();
    }
    catch (TrialAccountProvisioningException $e) {
      throw new ConfigActionException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
