<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Project Browser's update paths.
 */
#[Group('project_browser')]
#[Group('Update')]
#[RunTestsInSeparateProcesses]
final class UpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $fixture = $this->root . '/core/modules/system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz';

    $this->databaseDumpFiles = [
      // The 11.3 fixture is not available in Drupal 11.3 and earlier.
      file_exists($fixture) ? $fixture : str_replace('11.3', '10.3', $fixture),
      __DIR__ . '/../../fixtures/project_browser-2.1.0-beta2-installed.php',
    ];
  }

  /**
   * Tests the update path.
   */
  public function test(): void {
    // Needed for this test to work on D12: History needs to be removed from
    // the installed site.
    $this->config('core.extension')->clear('module.history')->save();

    $this->assertIsArray($this->config('project_browser.admin_settings')->get('allowed_projects'));
    $this->runUpdates();
    $this->assertNull($this->config('project_browser.admin_settings')->get('allowed_projects'));
  }

}
