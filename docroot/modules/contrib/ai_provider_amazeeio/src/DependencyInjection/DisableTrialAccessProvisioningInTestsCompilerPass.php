<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\DependencyInjection;

use Drupal\Core\Test\TestRunnerKernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Disables trial access key provisioning in tests.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class DisableTrialAccessProvisioningInTestsCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    if ($container->getParameter('ai_provider_amazeeio.internal.disable_environment_detection')) {
      return;
    }

    $disable_trial_access_provisioning = FALSE;

    // Kernel tests and unit tests: "testing" environment
    // (or no kernel env set).
    // This also covers unit tests with a handcrafted ContainerBuilder.
    if (!$container->hasParameter('kernel.environment') || $container->getParameter('kernel.environment') === 'testing') {
      $disable_trial_access_provisioning = TRUE;
    }
    // Functional tests: early boot.
    elseif ($container->get('kernel') instanceof TestRunnerKernel) {
      $disable_trial_access_provisioning = TRUE;
    }
    // Functional tests: after boot, where the test user agent is available.
    elseif (\drupal_valid_test_ua()) {
      $disable_trial_access_provisioning = TRUE;
    }

    $container->setParameter('ai_provider_amazeeio.trial_access_provisioning.disable', $disable_trial_access_provisioning);
  }

}
