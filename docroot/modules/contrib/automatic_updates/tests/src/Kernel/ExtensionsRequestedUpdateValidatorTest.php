<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel;

use Drupal\automatic_updates\ExtensionUpdateSandboxManager;
use Drupal\automatic_updates\Validator\RequestedUpdateValidator;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Exception\SandboxEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\update\Functional\UpdateTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(RequestedUpdateValidator::class)]
#[RunTestsInSeparateProcesses]
class ExtensionsRequestedUpdateValidatorTest extends AutomaticUpdatesExtensionsKernelTestBase {

  use UpdateTestTrait;

  /**
   * Tests error messages if requested updates were not staged.
   *
   * @param array $staged_versions
   *   An array of the staged versions where the keys are the package names and
   *   the values are the package versions.
   * @param array $expected_results
   *   The expected validation results.
   */
  #[DataProvider('providerTestErrorMessage')]
  public function testErrorMessage(array $staged_versions, array $expected_results): void {
    if ($staged_versions) {
      // If we are going to stage updates to Drupal packages also update a
      // non-Drupal. The validator should ignore the non-Drupal packages.
      (new ActiveFixtureManipulator())
        ->addPackage([
          "name" => 'vendor/non-drupal-package',
          "version" => "1.0.0",
          "type" => "drupal-module",
        ])
        ->commitChanges();
      $this->getStageFixtureManipulator()->setVersion('vendor/non-drupal-package', '1.0.1');
      foreach ($staged_versions as $package => $version) {
        $this->getStageFixtureManipulator()->setVersion($package, $version);
      }
    }

    $package_manager_dir = static::getDrupalRoot() . '/core/modules/package_manager';
    $this->setReleaseMetadata([
      'semver_test' => __DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml',
      'drupal' => "$package_manager_dir/tests/fixtures/release-history/drupal.9.8.2.xml",
      'aaa_update_test' => "$package_manager_dir/tests/fixtures/release-history/aaa_update_test.1.1.xml",
    ]);
    // Set the project version to '8.0.1' so that there 2 versions of above this
    // that will be in the list of supported releases, 8.1.0 and 8.1.1.
    (new ActiveFixtureManipulator())
      ->setVersion('drupal/semver_test', '8.0.1')
      ->commitChanges();
    $this->mockInstalledExtensionsInfo([
      'semver_test' => [
        'version' => '8.0.1',
        'project' => 'semver_test',
      ],
    ]);

    $stage = $this->container->get(ExtensionUpdateSandboxManager::class);
    $stage->begin([
      'semver_test' => '8.1.1',
      'aaa_update_test' => '8.x-1.1',
    ]);
    $stage->stage();
    $this->assertStatusCheckResults($expected_results, $stage);
    try {
      $stage->apply();
      $this->fail('Expecting an exception.');
    }
    catch (SandboxEventException $exception) {
      $this->assertExpectedResultsFromException($expected_results, $exception);
    }
  }

  /**
   * Data provider for testErrorMessage().
   *
   * @return mixed[]
   *   The test cases.
   */
  public static function providerTestErrorMessage() {
    return [
      'no updates' => [
        [],
        [
          ValidationResult::createError([t('No updates detected in the staging area.')]),
        ],
      ],
      '1 project not updated' => [
        [
          'drupal/aaa_update_test' => '1.1.0',
        ],
        [
          ValidationResult::createError([t("The requested update to 'drupal/semver_test' to version '8.1.1' was not performed.")]),
        ],
      ],
      'project updated to wrong version' => [
        [
          'drupal/semver_test' => '8.1.0',
          'drupal/aaa_update_test' => '1.1.0',
        ],
        [
          ValidationResult::createError([t("The requested update to 'drupal/semver_test' to version '8.1.1' does not match the actual staged update to '8.1.0'.")]),
        ],
      ],
    ];
  }

}
