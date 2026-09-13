<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\Form\ExtensionsUpdaterForm;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(ExtensionsUpdaterForm::class)]
#[RunTestsInSeparateProcesses]
final class ExtensionsStatusCheckerRunAfterUpdateTest extends ExtensionsUpdaterFormTestBase {

  /**
   * Data provider for testStatusCheckerRunAfterUpdate().
   *
   * @return bool[][]
   *   The test cases.
   */
  public static function providerStatusCheckerRunAfterUpdate(): array {
    return [
      'has database updates' => [TRUE],
      'does not have database updates' => [FALSE],
    ];
  }

  /**
   * Tests status checks are run after an update.
   *
   * @param bool $has_database_updates
   *   Whether the site has database updates or not.
   */
  #[DataProvider('providerStatusCheckerRunAfterUpdate')]
  public function testStatusCheckerRunAfterUpdate(bool $has_database_updates): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->checkForUpdates();
    $page = $this->getSession()->getPage();
    // Navigate to the automatic updates form.
    $this->drupalGet('/admin/modules/update');
    $assert_session = $this->assertSession();
    $assert_session->pageTextNotContains(static::$errorsExplanation);
    $assert_session->pageTextNotContains(static::$warningsExplanation);

    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->getStageFixtureManipulator()->setVersion('drupal/semver_test_package_name', '8.1.1');
    $this->assertUpdatesCount(1);
    $page->checkField('projects[semver_test]');
    $page->pressButton('Update');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);
    // Set an error before completing the update. This error should be visible
    // on admin pages after completing the update without having to explicitly
    // run the status checks.
    TestSubscriber1::setTestResult([
      ValidationResult::createError([
        new TranslatableMarkup('Error before continue.'),
      ]),
    ], StatusCheckEvent::class);
    if ($has_database_updates) {
      // Simulate a staged database update in the automatic_updates_test module.
      // We must do this after the update has started, because the pending
      // updates validator will prevent an update from starting.
      $this->container->get('state')->set('automatic_updates_test.new_update', TRUE);
      $this->acceptWarningAndUpdate();
      $assert_session->pageTextContainsOnce('An error has occurred.');
      $assert_session->pageTextContainsOnce('Continue to the error page');
      $page->clickLink('the error page');
      $assert_session->pageTextContains('Some modules have database updates pending. You should run the database update script immediately.');
      $assert_session->linkExists('database update script');
      $assert_session->linkByHrefExists('/update.php');
      $page->clickLink('database update script');
      $this->assertSession()->addressEquals('/update.php');
      $assert_session->pageTextNotContains('Possible database updates have been detected in the following extension');
      $page->clickLink('Continue');
      // @see automatic_updates_update_1191934()
      $assert_session->pageTextContains('Dynamic automatic_updates_update_1191934');
      $page->clickLink('Apply pending updates');
      $this->checkForMetaRefresh();
      $assert_session->pageTextContains('Updates were attempted.');
    }
    else {
      $this->acceptWarningAndUpdate();
      $assert_session->addressEquals('/admin/reports/updates');
      $assert_session->pageTextContainsOnce('Update complete!');
    }
    // Status checks should display errors on admin page.
    $this->drupalGet('/admin');
    // Confirm that the status checks were run and the new error is displayed.
    $assert_session->statusMessageContains('Error before continue.', 'error');
    $assert_session->statusMessageContains('Your site does not pass some readiness checks for automatic updates. It cannot be automatically updated until further action is performed.', 'error');
    $assert_session->pageTextNotContains('Your site has not recently run an update readiness check. Rerun readiness checks now.');
  }

}
