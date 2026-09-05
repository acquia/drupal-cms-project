<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\Form\UpdaterForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(UpdaterForm::class)]
#[RunTestsInSeparateProcesses]
class UpdateWarningTest extends UpdaterFormTestBase {

  /**
   * Tests that update can be completed even if a status check throws a warning.
   */
  public function testContinueOnWarning(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $session = $this->getSession();

    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');
    $session->getPage()->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);

    $messages = [
      new TranslatableMarkup("The only thing we're allowed to do is to"),
      new TranslatableMarkup("believe that we won't regret the choice"),
      new TranslatableMarkup("we made."),
    ];
    $summary = new TranslatableMarkup('some generic summary');
    $warning = ValidationResult::createWarning($messages, $summary);
    TestSubscriber::setTestResult([$warning], StatusCheckEvent::class);
    $session->reload();

    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Continue');
    $this->assertStatusMessageContainsResult($warning);

    // A warning with only one message should also show its summary.
    $warning = ValidationResult::createWarning([
      new TranslatableMarkup("I'm still warning you."),
    ], $summary);
    TestSubscriber::setTestResult([$warning], StatusCheckEvent::class);
    $session->reload();
    $this->assertStatusMessageContainsResult($warning);
    $assert_session->buttonExists('Continue');
  }

}
