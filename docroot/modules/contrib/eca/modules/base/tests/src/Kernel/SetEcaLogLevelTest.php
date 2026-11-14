<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca_base\Plugin\Action\SetEcaLogLevel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_set_eca_log_level" action plugin.
 */
#[Group('eca')]
#[Group('eca_base')]
#[RunTestsInSeparateProcesses]
class SetEcaLogLevelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
    'modeler_api',
  ];

  /**
   * Tests that the action remains available when ECA config is absent.
   */
  public function testActionWithoutEcaConfig(): void {
    /** @var \Drupal\eca\Service\Actions $actionService */
    $actionService = $this->container->get('eca.service.action');
    $actions = $actionService->actions();
    $actionIds = array_map(
      static fn (ActionInterface $action): string => $action->getPluginId(),
      $actions,
    );
    $this->assertContains('eca_set_eca_log_level', $actionIds);

    $action = $actionService->createInstance('eca_set_eca_log_level');
    $this->assertInstanceOf(SetEcaLogLevel::class, $action);

    $configuredLogLevel = new \ReflectionProperty(
      SetEcaLogLevel::class,
      'configuredLogLevel',
    );
    $this->assertSame(RfcLogLevel::ERROR, $configuredLogLevel->getValue($action));
  }

}
