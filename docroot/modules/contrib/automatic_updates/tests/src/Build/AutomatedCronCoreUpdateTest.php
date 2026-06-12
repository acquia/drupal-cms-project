<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\automatic_updates\ConsoleUpdateSandboxManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests using automated_cron to update core.
 */
#[Group('automatic_updates')]
final class AutomatedCronCoreUpdateTest extends CoreUpdateTestBase {

  /**
   * Tests updating during cron using the Automated Cron module.
   */
  public function testAutomatedCron(): void {
    $this->createTestProject('RecommendedProject');
    $this->installModules(['automated_cron']);

    // Reset the record of the last cron run.
    $this->visit('/automatic-updates-test-api/reset-cron');
    $this->getMink()->assertSession()->pageTextContains('cron reset');
    // Make another request so that Automated Cron will be triggered at the end
    // of the request.
    $this->visit('/');
    $this->assertExpectedStageEventsFired(ConsoleUpdateSandboxManager::class, wait: 360);
    $this->assertCronUpdateSuccessful();
  }

}
