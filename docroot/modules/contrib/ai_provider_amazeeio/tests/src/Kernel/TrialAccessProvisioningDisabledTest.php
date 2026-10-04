<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\ai_provider_amazeeio\TrialAccess\NullProgressReporter;
use Drupal\ai_provider_amazeeio\TrialAccess\NullTrialAccessProvisioner;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Checks that trial access key provisioning is disabled in kernel tests.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class TrialAccessProvisioningDisabledTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_provider_amazeeio',
  ];

  /**
   * Tests null instance is returned.
   */
  public function test(): void {
    self::assertInstanceOf(NullTrialAccessProvisioner::class, $this->container->get(TrialAccountProvisionerFactoryInterface::class)->create(new NullProgressReporter()));
  }

}
