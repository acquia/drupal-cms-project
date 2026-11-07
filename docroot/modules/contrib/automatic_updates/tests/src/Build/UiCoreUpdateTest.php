<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\automatic_updates\UpdateSandboxManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests updating core via the UI.
 */
#[Group('automatic_updates')]
final class UiCoreUpdateTest extends CoreUpdateTestBase {

  /**
   * Tests an end-to-end core update via the UI.
   */
  public function testUi(): void {
    $this->createTestProject('RecommendedProject');

    $mink = $this->getMink();
    $session = $mink->getSession();
    $page = $session->getPage();
    $assert_session = $mink->assertSession();
    $this->coreUpdateTillUpdateReady($page);
    $page->pressButton('Continue');
    $this->waitForBatchJob();
    $assert_session->addressEquals('/admin/reports/updates');
    $assert_session->pageTextContains('Update complete!');
    $assert_session->pageTextContains('Up to date');
    $assert_session->pageTextNotContains('There is a security update available for your version of Drupal.');
    $this->assertExpectedStageEventsFired(UpdateSandboxManager::class);
    $this->assertUpdateSuccessful('9.8.1');
    $this->assertRequestedChangesWereLogged([
      'Update drupal/core-dev from 9.8.0 to 9.8.1',
      'Update drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
    $this->assertAppliedChangesWereLogged([
      'Updated drupal/core from 9.8.0 to 9.8.1',
      'Updated drupal/core-dev from 9.8.0 to 9.8.1',
      'Updated drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
  }

}
