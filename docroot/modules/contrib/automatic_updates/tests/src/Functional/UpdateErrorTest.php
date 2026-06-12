<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\Form\UpdaterForm;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\Event\SandboxValidationEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 *
 * @todo Consolidate and remove duplicate test coverage in
 *   https://drupal.org/i/3354325.
 */
#[Group('automatic_updates')]
#[CoversClass(UpdaterForm::class)]
#[RunTestsInSeparateProcesses]
class UpdateErrorTest extends UpdaterFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.logging')
      ->set('error_level', ERROR_REPORTING_DISPLAY_VERBOSE)
      ->save();
  }

  /**
   * Tests that update cannot be completed via the UI if a status check fails.
   */
  public function testStatusCheckErrorPreventsUpdate(): void {
    $session = $this->getSession();
    $assert_session = $this->assertSession();
    $page = $session->getPage();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);

    $error_messages = [
      new TranslatableMarkup("The only thing we're allowed to do is to"),
      new TranslatableMarkup("believe that we won't regret the choice"),
      new TranslatableMarkup("we made."),
    ];
    $summary = new TranslatableMarkup('some generic summary');
    $error = ValidationResult::createError($error_messages, $summary);
    TestSubscriber::setTestResult([$error], StatusCheckEvent::class);
    $this->getSession()->reload();
    $this->assertStatusMessageContainsResult($error);
    $assert_session->buttonNotExists('Continue');
    $assert_session->buttonExists('Cancel update');

    // An error with only one message should also show the summary.
    $error = ValidationResult::createError([
      new TranslatableMarkup('Yet another smarmy error.'),
    ], $summary);
    TestSubscriber::setTestResult([$error], StatusCheckEvent::class);
    $this->getSession()->reload();
    $this->assertStatusMessageContainsResult($error);
    $assert_session->buttonNotExists('Continue');
    $assert_session->buttonExists('Cancel update');
  }

  /**
   * Tests that throwables will be displayed properly.
   */
  public function testDisplayErrorCreatedFromThrowable(): void {
    $throwable = new \Exception("I want to be the pirate king because he's the freest man alive.");
    $result = ValidationResult::createErrorFromThrowable($throwable);
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);
    $this->drupalGet('/admin/reports/status');
    $this->clickLink('Rerun readiness checks');
    $this->drupalGet('/admin');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->statusMessageContains($throwable->getMessage(), 'error');
  }

  /**
   * Tests the display of errors and warnings during status check.
   */
  public function testStatusCheckErrorDisplay(): void {
    $session = $this->getSession();
    $assert_session = $this->assertSession();

    $cached_message = $this->setAndAssertCachedMessage();
    // Ensure that the fake error is cached.
    $session->reload();
    $assert_session->pageTextContainsOnce((string) $cached_message);

    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();

    // Set up a new fake error. Use an error with multiple messages so we can
    // ensure that they're all displayed, along with their summary.
    $expected_results = [$this->createValidationResult(RequirementSeverity::Error->value, 2)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);

    // If a validator raises an error during status checking, the form should
    // not have a submit button.
    $this->drupalGet('/admin/reports/updates/update');
    $this->assertNoUpdateButtons();
    // Since this is an administrative page, the error message should be visible
    // thanks to automatic_updates_page_top(). The status checks were re-run
    // during the form build, which means the new error should be cached and
    // displayed instead of the previously cached error.
    $this->assertStatusMessageContainsResult($expected_results[0]);
    $assert_session->pageTextContainsOnce(static::$errorsExplanation);
    $assert_session->pageTextNotContains(static::$warningsExplanation);
    $assert_session->pageTextNotContains($cached_message->render());
    TestSubscriber1::setTestResult(NULL, StatusCheckEvent::class);

    // Set up an error with one message and a summary. We should see both when
    // we refresh the form.
    $expected_result = $this->createValidationResult(RequirementSeverity::Error->value, 1);
    TestSubscriber1::setTestResult([$expected_result], StatusCheckEvent::class);
    $this->getSession()->reload();
    $this->assertNoUpdateButtons();
    $this->assertStatusMessageContainsResult($expected_result);
    $assert_session->pageTextContainsOnce(static::$errorsExplanation);
    $assert_session->pageTextNotContains(static::$warningsExplanation);
    $assert_session->pageTextNotContains($cached_message->render());
    TestSubscriber1::setTestResult(NULL, StatusCheckEvent::class);
  }

  /**
   * Tests handling of exceptions and errors raised by event subscribers.
   *
   * @param string $event
   *   The event that should cause a problem.
   * @param string $stopped_by
   *   Either 'exception' to throw an exception on the given event, or
   *   'validation error' to flag a validation error instead.
   */
  #[DataProvider('providerUpdateStoppedByEventSubscriber')]
  public function testUpdateStoppedByEventSubscriber(string $event, string $stopped_by): void {
    if ($stopped_by === 'validation error') {
      $result = ValidationResult::createError([
        new TranslatableMarkup('Bad news bears!'),
      ]);
      TestSubscriber::setTestResult([$result], $event);
    }
    else {
      $this->assertSame('exception', $stopped_by);
      TestSubscriber::setException(new \Exception('Bad news bears!'), $event);
    }

    // Only simulate a staged update if we're going to get far enough that the
    // stage directory will be created.
    if ($event !== StatusCheckEvent::class && $event !== PreCreateEvent::class) {
      $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    }

    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');

    // StatusCheckEvent runs very early, before we can even start the update.
    // If it raises the error we're expecting, we're done.
    if ($event === StatusCheckEvent::class) {
      // If we are flagging a validation error, we should see an explanatory
      // message. If we're throwing an exception, we shouldn't.
      if ($stopped_by === 'validation error') {
        $assert_session->statusMessageContains(static::$errorsExplanation, 'error');
      }
      else {
        $assert_session->pageTextNotContains(static::$errorsExplanation);
      }
      $assert_session->pageTextNotContains(static::$warningsExplanation);
      $assert_session->statusMessageContains('Bad news bears!', 'error');
      // We shouldn't be able to start the update.
      $assert_session->buttonNotExists('Update to 9.8.1');
      return;
    }

    // Start the update.
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    // If the batch job fails, proceed to the error page. If it failed because
    // of the exception we set up, we're done.
    if ($page->hasLink('the error page')) {
      // We should see the exception's backtrace.
      $assert_session->responseContains('<pre class="backtrace">');
      $page->clickLink('the error page');
      $assert_session->statusMessageContains('Bad news bears!', 'error');
      // We should be on the start page.
      $assert_session->addressEquals('/admin/reports/updates/update');

      // If we failed during post-create, the stage is not destroyed, so we
      // should not be able to start the update anew without destroying the
      // stage first. In all other cases, the stage should have been destroyed
      // (or never created at all) and we should be able to try again.
      // @todo Delete the existing update on behalf of the user in
      //   https://drupal.org/i/3346644.
      if ($event === PostCreateEvent::class) {
        $assert_session->pageTextContains('Cannot begin an update because another Composer operation is currently in progress.');
        $assert_session->buttonNotExists('Update to 9.8.1');
        $assert_session->buttonExists('Delete existing update');
      }
      else {
        $assert_session->pageTextNotContains('Cannot begin an update because another Composer operation is currently in progress.');
        $assert_session->buttonExists('Update to 9.8.1');
        $assert_session->buttonNotExists('Delete existing update');
      }
      return;
    }

    // We should now be ready to finish the update.
    $this->assertStringContainsString('/admin/automatic-update-ready/', $session->getCurrentUrl());
    // Ensure that we are expecting a failure from an event that is dispatched
    // during the second phase (apply and destroy) of the update.
    $this->assertContains($event, [
      PreApplyEvent::class,
      PostApplyEvent::class,
    ]);
    // Try to finish the update.
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    // As we did before, proceed to the error page if the batch job fails. If it
    // failed because of the exception we set up, we're done here.
    if ($page->hasLink('the error page')) {
      // We should see the exception's backtrace.
      $assert_session->responseContains('<pre class="backtrace">');
      $page->clickLink('the error page');
      // We should be back on the "ready to update" page, and the exception
      // message should be visible.
      $this->assertStringContainsString('/admin/automatic-update-ready/', $session->getCurrentUrl());
      $assert_session->statusMessageContains('Bad news bears!', 'error');
    }
  }

  /**
   * Data provider for ::testUpdateStoppedByEventSubscriber().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerUpdateStoppedByEventSubscriber(): array {
    $events = [
      StatusCheckEvent::class,
      PreCreateEvent::class,
      PostCreateEvent::class,
      PreRequireEvent::class,
      PostRequireEvent::class,
      PreApplyEvent::class,
      PostApplyEvent::class,
    ];
    $data = [];
    foreach ($events as $event) {
      $data["exception from $event"] = [$event, 'exception'];

      // Only the pre-operation events support flagging validation errors.
      if (is_subclass_of($event, SandboxValidationEvent::class)) {
        $data["validation error from $event"] = [$event, 'validation error'];
      }
    }
    return $data;
  }

}
