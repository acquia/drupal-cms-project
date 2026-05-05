<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Project Browser's update paths.
 */
#[Group('project_browser')]
#[Group('Update')]
final class UpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      $this->getDrupalRoot() . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../fixtures/project_browser-2.1.0-beta2-installed.php',
    ];
  }

  /**
   * Tests the update path.
   */
  public function test(): void {
    $this->assertIsArray($this->config('project_browser.admin_settings')->get('allowed_projects'));
    $this->runUpdates();
    $this->assertNull($this->config('project_browser.admin_settings')->get('allowed_projects'));
  }

}
