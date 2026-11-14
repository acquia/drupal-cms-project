<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio;

use Drupal\ai_provider_amazeeio\DependencyInjection\DisableTrialAccessProvisioningInTestsCompilerPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Module-specific service provider.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class AiProviderAmazeeioServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new DisableTrialAccessProvisioningInTestsCompilerPass());
  }

}
