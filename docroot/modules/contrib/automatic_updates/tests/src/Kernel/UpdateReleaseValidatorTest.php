<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel;

use Drupal\automatic_updates\Validator\UpdateReleaseValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager\Event\PreCreateEvent;
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
#[CoversClass(UpdateReleaseValidator::class)]
#[RunTestsInSeparateProcesses]
final class UpdateReleaseValidatorTest extends AutomaticUpdatesExtensionsKernelTestBase {

  use UpdateTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Data provider for testPreCreateException().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerTestPreCreateException(): array {
    return [
      'semver, supported update' => ['semver_test', '8.1.0', '8.1.1', FALSE],
      'semver, update to unsupported branch' => ['semver_test', '8.1.0', '8.2.0', TRUE],
      'legacy, supported update' => ['aaa_update_test', '8.x-2.0', '8.x-2.1', FALSE],
      'legacy, update to unsupported branch' => ['aaa_update_test', '8.x-2.0', '8.x-3.0', TRUE],
    ];
  }

  /**
   * Tests updating to a release during pre-create.
   *
   * @param string $project
   *   The project to update.
   * @param string $installed_version
   *   The installed version of the project.
   * @param string $target_version
   *   The target version.
   * @param bool $error_expected
   *   Whether an error is expected in the update.
   */
  #[DataProvider('providerTestPreCreateException')]
  public function testPreCreateException(string $project, string $installed_version, string $target_version, bool $error_expected): void {
    $this->enableModules([$project]);

    $this->mockInstalledExtensionsInfo([
      $project => [
        'version' => $installed_version,
        'project' => $project,
      ],
    ]);

    $package_manager_dir = static::getDrupalRoot() . '/core/modules/package_manager';
    $path_to_fixtures_folder = $project === 'aaa_update_test'
      ? "$package_manager_dir/tests/"
      : __DIR__ . '/../../';
    $this->setReleaseMetadata([
      $project => $path_to_fixtures_folder . "/fixtures/release-history/$project.1.1.xml",
      'drupal' => "$package_manager_dir/tests/fixtures/release-history/drupal.9.8.2.xml",
    ]);

    if ($error_expected) {
      $expected_results = [
        ValidationResult::createError(
          [
            new TranslatableMarkup('Project @project to version @target_version', [
              '@project' => $project,
              '@target_version' => LegacyVersionUtility::convertToSemanticVersion($target_version),
            ]),
          ],
          new TranslatableMarkup('Cannot update because the following project version is not in the list of installable releases.')
        ),
      ];
    }
    else {
      // Ensure the correct version of the package is staged because the update
      // is expected to succeed.
      $this->getStageFixtureManipulator()->setVersion("drupal/$project", LegacyVersionUtility::convertToSemanticVersion($target_version));
      $expected_results = [];
    }

    $this->assertUpdateResults([$project => $target_version], $expected_results, PreCreateEvent::class);
  }

}
