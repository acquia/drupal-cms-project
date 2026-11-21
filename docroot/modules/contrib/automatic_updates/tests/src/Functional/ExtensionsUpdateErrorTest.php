<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\Form\ExtensionsUpdaterForm;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_bypass\LoggingCommitter;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(ExtensionsUpdaterForm::class)]
#[RunTestsInSeparateProcesses]
final class ExtensionsUpdateErrorTest extends ExtensionsUpdaterFormTestBase {

  /**
   * Tests that an exception is thrown if a previous apply failed.
   */
  public function testMarkerFileFailure(): void {
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
    $assert_session->pageTextNotContains('The following dependencies will also be updated:');
    LoggingCommitter::setException(\Exception::class, 'failed at committer');
    $this->acceptWarningAndUpdate();
    $failure_message = 'Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.';
    $assert_session->pageTextContainsOnce('An error has occurred.');
    $assert_session->pageTextContains($failure_message);
    $page->clickLink('the error page');

    // We should be on the form (i.e., 200 response code), but unable to
    // continue the update.
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($failure_message);
    $assert_session->buttonNotExists('Continue');
    // The same thing should be true if we try to start from the beginning.
    $this->drupalGet('/admin/modules/update');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($failure_message);
    $assert_session->buttonNotExists('Update');
  }

  /**
   * Test the form for errors.
   */
  public function testErrors(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $assert = $this->assertSession();
    $user = $this->createUser([
      'administer site configuration',
      'administer software updates',
    ]);
    $this->drupalLogin($user);
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/updates/update');
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->assertUpdatesCount(1);
    $message = new TranslatableMarkup("You've not experienced Shakespeare until you have read him in the original Klingon.");
    $error = ValidationResult::createError([$message]);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->getSession()->reload();
    $assert->pageTextContains((string) $message);
    $assert->pageTextContains(static::$errorsExplanation);
    $assert->pageTextNotContains(static::$warningsExplanation);
    $assert->buttonNotExists('Update');
  }

  /**
   * Tests that StatusCheckEvent messages are shown on the confirmation form.
   */
  public function testStatusErrorMessages(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $assert = $this->assertSession();
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/updates/update');
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->assertUpdatesCount(1);
    $this->getSession()->reload();
    $assert->elementExists('css', '#edit-projects-semver-test')->check();
    $assert->checkboxChecked('edit-projects-semver-test');
    $assert->buttonExists('Update');
    $messages = [
      new TranslatableMarkup("The only thing we're allowed to do is to"),
      new TranslatableMarkup("believe that we won't regret the choice"),
      new TranslatableMarkup("we made."),
    ];
    $summary = new TranslatableMarkup('Some summary');
    $error = ValidationResult::createError($messages, $summary);
    TestSubscriber::setTestResult([$error], StatusCheckEvent::class);
    $this->getSession()->getPage()->pressButton('Update');
    $this->checkForMetaRefresh();
    $assert->pageTextContains(static::$errorsExplanation);
    $assert->pageTextNotContains(static::$warningsExplanation);
    $assert->pageTextContains((string) $summary);
    foreach ($messages as $message) {
      $assert->pageTextContains((string) $message);
    }
    $assert->buttonNotExists('Continue');
  }

}
