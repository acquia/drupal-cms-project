<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Project Browser's DB tables are created and destroyed correctly.
 *
 * @group project_browser
 */
#[CoversFunction('project_browser_test_schema')]
#[Group('project_browser')]
final class DatabaseTablesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser',
    'project_browser_test',
    'user',
  ];

  /**
   * Tests that Project Browser's DB tables are created and destroyed correctly.
   */
  public function testDatabaseSchemaCreationAndDestruction(): void {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');
    $this->installSchema('project_browser_test', [
      'project_browser_projects',
      'project_browser_categories',
    ]);
    $this->installConfig('project_browser_test');
    $this->container->get('module_handler')->loadInclude('project_browser_test', 'install');
    $module_installer->install(['project_browser_test']);
    // Hooks are not ran on kernel tests, so trigger it.
    project_browser_test_install();
    $this->container = \Drupal::getContainer();

    /** @var \Drupal\Core\Database\Schema $schema */
    $schema = $this->container->get('database')->schema();
    $this->assertTrue($schema->tableExists('project_browser_projects'));
    $this->assertTrue($schema->tableExists('project_browser_categories'));

    // Make sure the fixture files do have data in them.
    /** @var \Drupal\Core\Database\Connection $database */
    $database = $this->container->get('database');
    $rows = $database->select('project_browser_projects')->countQuery()->execute()?->fetchCol();
    $this->assertIsArray($rows);
    $this->assertGreaterThan(1, $rows[0]);
    $rows = $database->select('project_browser_categories')->countQuery()->execute()?->fetchCol();
    $this->assertIsArray($rows);
    $this->assertGreaterThan(1, $rows[0]);
  }

}
