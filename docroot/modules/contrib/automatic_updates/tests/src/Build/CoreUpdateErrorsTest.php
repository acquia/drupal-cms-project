<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\automatic_updates\ConsoleUpdateSandboxManager;
use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests error handling during core updates.
 */
#[Group('automatic_updates')]
final class CoreUpdateErrorsTest extends CoreUpdateTestBase {

  /**
   * Tests stage is destroyed if not available and site is on insecure version.
   */
  public function testStageDestroyedIfNotAvailable(): void {
    $this->createTestProject('RecommendedProject');
    $mink = $this->getMink();
    $session = $mink->getSession();
    $page = $session->getPage();
    $assert_session = $mink->assertSession();
    $this->coreUpdateTillUpdateReady($page);
    $this->visit('/admin/reports/status');
    $assert_session->pageTextContains('Your site is ready for automatic updates.');
    $page->clickLink('Run cron');
    // The stage will first destroy the stage made above before going through
    // stage lifecycle events for the cron update.
    $expected_events = [
      PreCreateEvent::class,
      PostCreateEvent::class,
      PreRequireEvent::class,
      PostRequireEvent::class,
      PreApplyEvent::class,
      PostApplyEvent::class,
    ];
    $this->assertExpectedStageEventsFired(ConsoleUpdateSandboxManager::class, $expected_events, 360);
    $this->assertCronUpdateSuccessful();
  }

}
