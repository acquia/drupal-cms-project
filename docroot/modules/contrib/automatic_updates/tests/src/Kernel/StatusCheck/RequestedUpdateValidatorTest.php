<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\UpdateSandboxManager;
use Drupal\automatic_updates\Validator\RequestedUpdateValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Exception\SandboxEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(RequestedUpdateValidator::class)]
#[RunTestsInSeparateProcesses]
class RequestedUpdateValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
  ];

  /**
   * Tests error message is shown if the core version is not updated.
   */
  public function testErrorMessageOnCoreNotUpdated(): void {
    // Update `drupal/core-recommended` to a version that does not match the
    // requested version of '9.8.1'. This also does not update all packages that
    // are expected to be updated when updating Drupal core.
    // @see \Drupal\automatic_updates\UpdateStage::begin()
    // @see \Drupal\package_manager\InstalledPackagesList::getCorePackages()
    $this->getStageFixtureManipulator()->setVersion('drupal/core-recommended', '9.8.2');
    $this->setCoreVersion('9.8.0');
    $this->setReleaseMetadata([
      'drupal' => static::getDrupalRoot() . '/core/modules/package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
    $this->container->get('module_installer')->install(['automatic_updates']);

    $sandbox_manager = $this->container->get(UpdateSandboxManager::class);
    $expected_results = [
      ValidationResult::createError([
        new TranslatableMarkup("The requested update to 'drupal/core-recommended' to version '9.8.1' does not match the actual staged update to '9.8.2'."),
      ]),
      ValidationResult::createError([
        new TranslatableMarkup("The requested update to 'drupal/core-dev' to version '9.8.1' was not performed."),
      ]),
    ];
    $sandbox_manager->begin(['drupal' => '9.8.1']);
    $this->assertStatusCheckResults($expected_results, $sandbox_manager);
    $sandbox_manager->stage();
    try {
      $sandbox_manager->apply();
      $this->fail('Expecting an exception.');
    }
    catch (SandboxEventException $exception) {
      $this->assertExpectedResultsFromException($expected_results, $exception);
    }
  }

  /**
   * Tests error message is shown if there are no core packages in stage.
   */
  public function testErrorMessageOnEmptyCorePackages(): void {
    $this->getStageFixtureManipulator()
      ->removePackage('drupal/core')
      ->removePackage('drupal/core-recommended')
      ->removePackage('drupal/core-dev', TRUE);

    $this->setCoreVersion('9.8.0');
    $this->setReleaseMetadata([
      'drupal' => static::getDrupalRoot() . '/core/modules/package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
    $this->container->get('module_installer')->install(['automatic_updates']);

    $expected_results = [
      ValidationResult::createError([
        new TranslatableMarkup('No updates detected in the staging area.'),
      ]),
    ];
    $sandbox_manager = $this->container->get(UpdateSandboxManager::class);
    $sandbox_manager->begin(['drupal' => '9.8.1']);
    $this->assertStatusCheckResults($expected_results, $sandbox_manager);
    $sandbox_manager->stage();
    try {
      $sandbox_manager->apply();
      $this->fail('Expecting an exception.');
    }
    catch (SandboxEventException $exception) {
      $this->assertExpectedResultsFromException($expected_results, $exception);
    }
  }

}
