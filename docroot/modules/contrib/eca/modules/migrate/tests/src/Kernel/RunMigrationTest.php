<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\eca_migrate\Plugin\Action\RunMigration;
use Drupal\eca_migrate_test\Plugin\migrate\id_map\TestIdMap;
use Drupal\migrate\Plugin\MigrationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_migrate_run_migration" action plugin.
 */
#[Group('eca')]
#[Group('eca_migrate')]
#[CoversClass(RunMigration::class)]
#[RunTestsInSeparateProcesses]
class RunMigrationTest extends MigrationActionTestBase {

  /**
   * Tests executing the RunMigration action.
   */
  public function testExecute(): void {
    $config = [
      'migration_id' => 'eca_migrate_test_migration',
      'update' => FALSE,
    ];
    /** @var \Drupal\eca_migrate\Plugin\Action\RunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_run_migration', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationManager->createInstance('eca_migrate_test_migration');

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

  /**
   * Tests executing the RunMigration action with invalid migration ID.
   */
  public function testExecuteInvalidMigrationId(): void {
    $config = [
      'migration_id' => '',
      'update' => FALSE,
    ];
    /** @var \Drupal\eca_migrate\Plugin\Action\RunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_run_migration', $config);
    $this->assertFalse($action->access(NULL), 'Access denied if empty migration ID.');

    $config = [
      'migration_id' => 'invalid_migration_id',
      'update' => FALSE,
    ];
    /** @var \Drupal\eca_migrate\Plugin\Action\RunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_run_migration', $config);
    $this->assertFalse($action->access(NULL), 'Access denied if invalid migration ID.');
  }

  /**
   * Tests token replacement in migration_id.
   */
  public function testTokenReplacement(): void {
    /** @var \Drupal\eca\Token\TokenInterface $tokenServices */
    $tokenServices = \Drupal::service('eca.token_services');

    $tokenServices->addTokenData('mid', 'eca_migrate_test_migration');
    $config = [
      'migration_id' => '[mid]',
      'update' => FALSE,
    ];
    /** @var \Drupal\eca_migrate\Plugin\Action\RunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_run_migration', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationManager->createInstance('eca_migrate_test_migration');

    $this->assertContains(
      $migration->getStatus(),
      [MigrationInterface::STATUS_IDLE],
      'Migration is in expected state.'
    );
    $this->assertEquals(
      MigrationInterface::RESULT_COMPLETED,
      $action->getMigrationResult(),
      'Migration executed.',
    );
  }

  /**
   * Tests call of IdMap::prepareUpdate() if update is set to true.
   */
  public function testPrepareUpdateIsCalledWhenUpdateIsTrue(): void {
    $config = [
      'migration_id' => 'eca_migrate_test_migration',
      'update' => TRUE,
    ];
    /** @var \Drupal\eca_migrate_test\Plugin\Action\TestRunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_test_run_migration', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $this->assertTrue(TestIdMap::$prepareUpdateCalled, 'prepareUpdate() was called.');
  }

  /**
   * Tests call of IdMap::prepareUpdate() if update is set to false.
   */
  public function testPrepareUpdateIsNotCalledWhenUpdateIsFalse(): void {
    $config = [
      'migration_id' => 'eca_migrate_test_migration',
      'update' => FALSE,
    ];
    /** @var \Drupal\eca_migrate_test\Plugin\Action\TestRunMigration $action */
    $action = $this->actionManager->createInstance('eca_migrate_test_run_migration', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $this->assertFalse(TestIdMap::$prepareUpdateCalled, 'prepareUpdate() was not called.');
  }

}
