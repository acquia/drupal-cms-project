<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Unit;

use Drupal\ai_provider_amazeeio\DependencyInjection\DisableTrialAccessProvisioningInTestsCompilerPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests env detection in DisableTrialAccessProvisioningInTestsCompilerPass.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CoversClass(DisableTrialAccessProvisioningInTestsCompilerPass::class)]
final class DisableTrialAccessProvisioningInTestsCompilerPassTest extends UnitTestCase {

  /**
   * Tests that trial provisioning is disabled when kernel env is missing.
   */
  public function testDisablesWhenKernelEnvMissingDefaultsToTesting(): void {
    $container = new ContainerBuilder();
    $container->setParameter('ai_provider_amazeeio.internal.disable_environment_detection', FALSE);

    $compiler = new DisableTrialAccessProvisioningInTestsCompilerPass();
    $compiler->process($container);

    self::assertTrue($container->getParameter('ai_provider_amazeeio.trial_access_provisioning.disable'));
  }

  /**
   * Tests that trial provisioning is disabled when kernel env is testing.
   */
  public function testDisablesWhenKernelEnvIsTesting(): void {
    $container = new ContainerBuilder();
    $container->setParameter('ai_provider_amazeeio.internal.disable_environment_detection', FALSE);
    $container->setParameter('kernel.environment', 'testing');

    $compiler = new DisableTrialAccessProvisioningInTestsCompilerPass();
    $compiler->process($container);

    self::assertTrue($container->getParameter('ai_provider_amazeeio.trial_access_provisioning.disable'));
  }

  /**
   * Tests trial provisioning is enabled in prod when no test UA is present.
   */
  public function testDoesNotDisableInProdWithoutTestUa(): void {
    $container = new ContainerBuilder();
    $container->setParameter('ai_provider_amazeeio.internal.disable_environment_detection', FALSE);
    $container->setParameter('kernel.environment', 'prod');
    $container->set('kernel', $this->createMock(KernelInterface::class));

    $compiler = new DisableTrialAccessProvisioningInTestsCompilerPass();
    $compiler->process($container);

    // In unit tests, drupal_valid_test_ua() should be false.
    self::assertFalse($container->getParameter('ai_provider_amazeeio.trial_access_provisioning.disable'));
  }

  /**
   * Tests that no parameter is set when environment detection is disabled.
   */
  public function testDoesNothingWhenDetectionDisabled(): void {
    $container = new ContainerBuilder();
    $container->setParameter('ai_provider_amazeeio.internal.disable_environment_detection', TRUE);
    $container->setParameter('kernel.environment', 'testing');

    $compiler = new DisableTrialAccessProvisioningInTestsCompilerPass();
    $compiler->process($container);

    self::assertFalse($container->hasParameter('ai_provider_amazeeio.trial_access_provisioning.disable'));
  }

}
