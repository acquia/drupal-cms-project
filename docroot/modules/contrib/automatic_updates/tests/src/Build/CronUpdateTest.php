<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\automatic_updates\ConsoleUpdateSandboxManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests updating core via cron.
 */
#[Group('automatic_updates')]
final class CronUpdateTest extends CoreUpdateTestBase {

  /**
   * Tests an end-to-end core update via cron.
   *
   * @param string $template
   *   The template project from which to build the test site.
   */
  #[DataProvider('providerTemplate')]
  public function testCron(string $template): void {
    $this->createTestProject($template);

    $this->visit('/admin/reports/status');
    $session = $this->getMink()->getSession();

    $session->getPage()->clickLink('Run cron');
    $this->assertSame(200, $session->getStatusCode());
    $this->assertExpectedStageEventsFired(ConsoleUpdateSandboxManager::class, wait: 360);
    $this->assertCronUpdateSuccessful();
  }

}
