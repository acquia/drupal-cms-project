<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\Plugin\ConfigAction;

use Drupal\ai_provider_amazeeio\TrialAccess\ProgressReporterFactoryInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
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
 * Provisioning the trial account is a FALLBACK, not a requirement: it must
 * never abort the recipe/install that depends on this config action. If
 * amazee.ai is at trial capacity (HTTP 429), rate-limiting, returning a
 * malformed payload, unreachable (network/DNS/timeout), or fails for any
 * other reason - including an unexpected error anywhere else in the
 * provisioning path - this action logs a warning and lets the install
 * continue, so the site can be configured with any AI provider afterwards.
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
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $container->get(TrialAccountProvisionerFactoryInterface::class),
      $container->get(ProgressReporterFactoryInterface::class),
      $container->get('logger.channel.ai_provider_amazeeio'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $progressReporter = $this->progressReporterFactory->forCurrentRuntime();
    try {
      $this->trialAccountProvisionerFactory->create($progressReporter)->provision();
    }
    catch (\Throwable $e) {
      // Provisioning a free anonymous trial account is a convenience, not a
      // requirement, so ANY failure here (a known provisioning error, or an
      // unexpected \Throwable anywhere in the provisioning path, e.g. from
      // the provider's post-setup or a Key/Config save) is treated as a
      // graceful fallback rather than an install-breaker.
      $this->logger->warning('Could not provision an amazee.ai trial account: @message. Configure an AI provider at /admin/config/ai/providers.', [
        '@message' => $e->getMessage(),
      ]);
      $progressReporter->info('Skipped amazee.ai trial account provisioning; configure an AI provider at /admin/config/ai/providers.');
    }
  }

}
