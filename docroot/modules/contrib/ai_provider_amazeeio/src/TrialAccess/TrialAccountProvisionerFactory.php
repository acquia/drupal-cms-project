<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Default trial account provisioner implementation.
 *
 * @internal
 */
final class TrialAccountProvisionerFactory implements TrialAccountProvisionerFactoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AiProviderPluginManager $aiProviderManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly AmazeeClient $apiClient,
    private readonly StateInterface $state,
    private readonly LoggerInterface $logger,
    private readonly bool $dryRun = FALSE,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function create(ProgressReporterInterface $progressReporter): TrialAccountProvisionerInterface {
    if ($this->dryRun) {
      return new NullTrialAccessProvisioner();
    }
    return new TrialAccountProvisioner(
      $this->entityTypeManager,
      $this->httpClient,
      $this->configFactory,
      $this->aiProviderManager,
      $this->moduleHandler,
      $this->apiClient,
      $this->state,
      $this->logger,
      $progressReporter,
    );
  }

}
