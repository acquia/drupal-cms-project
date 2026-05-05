<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca_migrate\Plugin\Action\MigrateSkipRow;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateSkipRowException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the MigrateSkipRow action plugin.
 */
#[Group('eca_migrate')]
#[Group('eca')]
#[CoversClass(MigrateSkipRow::class)]
#[RunTestsInSeparateProcesses]
class MigrateSkipRowTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'migrate',
    'eca_migrate',
    'modeler_api',
  ];

  /**
   * The action plugin manager.
   */
  protected ActionManager $actionManager;

  /**
   * The id of the plugin to test.
   */
  protected string $pluginId = 'eca_migrate_skip_row';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->actionManager = \Drupal::service('plugin.manager.action');
  }

  /**
   * Tests that the action plugin is discoverable.
   */
  public function testPluginDiscovery(): void {
    $this->assertTrue($this->actionManager->hasDefinition($this->pluginId));

    $definition = $this->actionManager->getDefinition($this->pluginId);
    $this->assertEquals('Migrate: Skip Row', (string) $definition['label']);
  }

  /**
   * Tests that the action throws MigrateSkipRowException.
   */
  public function testActionThrowsException(): void {
    /** @var \Drupal\eca_migrate\Plugin\Action\MigrateSkipRow $action */
    $action = $this->actionManager->createInstance($this->pluginId, [
      'message' => 'Test skip message',
      'save_to_map' => TRUE,
    ]);

    try {
      $action->execute();
      $this->fail('Expected MigrateSkipRowException was not thrown');
    }
    catch (MigrateSkipRowException $e) {
      $this->assertEquals('Test skip message', $e->getMessage());
      $this->assertTrue($e->getSaveToMap());
    }

    $action = $this->actionManager->createInstance($this->pluginId, [
      'message' => '',
      'save_to_map' => FALSE,
    ]);

    try {
      $action->execute();
      $this->fail('Expected MigrateSkipRowException was not thrown');
    }
    catch (MigrateSkipRowException $e) {
      $this->assertEquals('', $e->getMessage());
      $this->assertFalse($e->getSaveToMap());
    }
  }

  /**
   * Tests that handleExceptions returns TRUE.
   */
  public function testHandleExceptions(): void {
    /** @var \Drupal\eca_migrate\Plugin\Action\MigrateSkipRow $action */
    $action = $this->actionManager->createInstance($this->pluginId);

    $this->assertTrue($action->handleExceptions());
  }

  /**
   * Tests default configuration.
   */
  public function testDefaultConfiguration(): void {
    /** @var \Drupal\eca_migrate\Plugin\Action\MigrateSkipRow $action */
    $action = $this->actionManager->createInstance($this->pluginId);

    $config = $action->getConfiguration();
    $this->assertEquals('', $config['message']);
    $this->assertTrue($config['save_to_map']);
  }

  /**
   * Tests configuration form building.
   */
  public function testConfigurationForm(): void {
    /** @var \Drupal\eca_migrate\Plugin\Action\MigrateSkipRow $action */
    $action = $this->actionManager->createInstance($this->pluginId);

    $form = [];
    $form_state = $this->createStub(FormStateInterface::class);

    $form = $action->buildConfigurationForm($form, $form_state);

    $this->assertArrayHasKey('message', $form);
    $this->assertEquals('textfield', $form['message']['#type']);

    $this->assertArrayHasKey('save_to_map', $form);
    $this->assertEquals('checkbox', $form['save_to_map']['#type']);
  }

  /**
   * Tests action access.
   */
  public function testAccess(): void {
    /** @var \Drupal\eca_migrate\Plugin\Action\MigrateSkipRow $action */
    $action = $this->actionManager->createInstance($this->pluginId);

    $account = $this->createStub(AccountInterface::class);

    $this->assertTrue($action->access(NULL, $account));
  }

}
