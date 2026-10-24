<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\Form\ExtensionsUpdaterForm;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(ExtensionsUpdaterForm::class)]
#[RunTestsInSeparateProcesses]
final class ExtensionsPreUpdateTest extends ExtensionsUpdaterFormTestBase {

  /**
   * Tests the form when modules requiring an update not installed via composer.
   */
  public function testNonComposerProjects(): void {
    $this->setReleaseMetadata(static::getDrupalRoot() . '/core/modules/package_manager/tests/fixtures/release-history/aaa_update_test.1.1.xml');
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $this->config('update.settings')
      ->set('fetch.url', $this->baseUrl . '/test-release-history')
      ->save();
    $this->setProjectInstalledVersion(
      [
        'aaa_update_test' => '8.x-2.0',
        'semver_test' => '8.1.0',
      ]
    );
    // One module not installed through composer.
    (new ActiveFixtureManipulator())
      ->removePackage('drupal/aaa_update_test')
      ->commitChanges();
    $assert = $this->assertSession();
    $user = $this->createUser(
      [
        'administer site configuration',
        'administer software updates',
      ]
    );
    $this->drupalLogin($user);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/updates/update');
    $assert->pageTextContains('Other updates were found, but they must be performed manually. See the list of available updates for more information.');
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->assertUpdatesCount(1);

    // Both of the modules not installed through composer.
    (new ActiveFixtureManipulator())
      ->removePackage('drupal/semver_test_package_name')
      ->commitChanges();
    $this->getSession()->reload();
    $assert->pageTextContains('Updates were found, but they must be performed manually. See the list of available updates for more information.');
    $this->assertNoUpdates();
  }

  /**
   * Tests the form when a module requires an update.
   */
  public function testHasUpdate(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $assert = $this->assertSession();
    $user = $this->createUser(['administer site configuration']);
    $this->drupalLogin($user);
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->drupalGet('admin/reports/updates/update');
    $assert->statusCodeEquals(403);
    $user = $this->createUser(['administer software updates', 'administer site configuration']);
    $this->drupalLogin($user);
    $this->checkForUpdates();
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->assertUpdatesCount(1);
    $assert->statusCodeEquals(200);
    $assert->buttonExists('Update');
  }

  /**
   * Tests the form when there are no available updates.
   */
  public function testNoUpdate(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $user = $this->createUser([
      'administer site configuration',
      'administer software updates',
    ]);
    $this->drupalLogin($user);
    $this->setProjectInstalledVersion(['semver_test' => '8.1.1']);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/updates/update');
    $this->assertNoUpdates();
  }

}
