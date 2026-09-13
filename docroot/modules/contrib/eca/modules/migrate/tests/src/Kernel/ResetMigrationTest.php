<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\eca_migrate\Plugin\Action\ResetMigration;
use Drupal\migrate\Plugin\MigrationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_migrate_reset_migration" action plugin.
 */
#[Group('eca')]
#[Group('eca_migrate')]
#[CoversClass(ResetMigration::class)]
#[RunTestsInSeparateProcesses]
class ResetMigrationTest extends MigrationActionTestBase {

  /**
   * Tests executing the RunMigration action.
   */
  public function testExecute(): void {
    $config = [
      'migration_id' => 'eca_migrate_test_migration',
    ];
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationManager->createInstance('eca_migrate_test_migration');
    $migration->setStatus(MigrationInterface::STATUS_IMPORTING);

    $this->assertContains(
      $migration->getStatus(),
      [MigrationInterface::STATUS_IMPORTING],
      'Migration is in expected state.',
    );

    /** @var \Drupal\eca_migrate\Plugin\Action\RunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_reset_migration', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $this->assertContains(
      $migration->getStatus(),
      [MigrationInterface::STATUS_IDLE],
      'Migration is in expected state.',
    );
    $this->assertEquals(
      MigrationInterface::RESULT_COMPLETED,
      $action->getMigrationResult(),
      'Migration executed.',
    );
  }

}
