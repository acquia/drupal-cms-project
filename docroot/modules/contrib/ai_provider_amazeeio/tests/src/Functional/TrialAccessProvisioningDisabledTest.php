<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Functional;

use Drupal\ai_provider_amazeeio\TrialAccess\NullProgressReporter;
use Drupal\ai_provider_amazeeio\TrialAccess\NullTrialAccessProvisioner;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Checks that trial access key provisioning is disabled in functional tests.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class TrialAccessProvisioningDisabledTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_provider_amazeeio',
  ];

  /**
   * Tests null instance is returned.
   */
  public function test(): void {
    self::assertInstanceOf(NullTrialAccessProvisioner::class, $this->container->get(TrialAccountProvisionerFactoryInterface::class)->create(new NullProgressReporter()));
  }

}
