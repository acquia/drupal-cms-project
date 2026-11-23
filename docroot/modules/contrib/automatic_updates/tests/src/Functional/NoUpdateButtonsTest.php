<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\Form\UpdaterForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(UpdaterForm::class)]
#[RunTestsInSeparateProcesses]
class NoUpdateButtonsTest extends UpdaterFormTestBase {

  /**
   * Tests that the form doesn't display any buttons if Drupal is up-to-date.
   *
   * @todo Mark this test as skipped if the web server is PHP's built-in, single
   *   threaded server in https://drupal.org/i/3348251.
   */
  public function testFormNotDisplayedIfAlreadyCurrent(): void {
    $this->mockActiveCoreVersion('9.8.1');
    $this->checkForUpdates();

    $this->drupalGet('/admin/reports/updates/update');

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('No update available');
    $this->assertNoUpdateButtons();
  }

  /**
   * Tests that updating to a different minor version isn't supported.
   */
  public function testMinorVersionUpdateNotSupported(): void {
    $this->mockActiveCoreVersion('9.7.1');
    $this->checkForUpdates();

    $this->drupalGet('/admin/reports/updates/update');

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Updates were found, but they must be performed manually. See the list of available updates for more information.');
    $this->clickLink('the list of available updates');
    $assert_session->elementExists('css', 'table.update');
    $this->assertNoUpdateButtons();
  }

}
