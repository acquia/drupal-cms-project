<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ECA configuration schema definitions.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ConfigSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
    'eca_log',
    'eca_misc',
    'eca_queue',
    'modeler_api',
  ];

  /**
   * Tests the event plugin schema and event plugin validation.
   */
  public function testEventPluginSchema(): void {
    $typed_config_manager = $this->container->get('config.typed');
    $event_plugin_definition = $typed_config_manager->getDefinition('eca.event.plugin');
    $event_definition = $typed_config_manager->getDefinition('eca.eca.*');

    $this->assertArrayNotHasKey('id', $event_plugin_definition['mapping']);
    $this->assertSame([
      'manager' => 'plugin.manager.eca.event',
      'interface' => 'Drupal\\eca\\Plugin\\ECA\\Event\\EventInterface',
    ], $event_definition['mapping']['events']['sequence']['mapping']['plugin']['constraints']['PluginExists']);
  }

  /**
   * Tests integer configuration values that may be defined by a token.
   */
  public function testIntegerOrTokenSchema(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $dataDefinition = $typedConfigManager->createDataDefinition('eca_integer_or_token');
    foreach ([6, '_eca_token', '[integer_value]'] as $value) {
      $this->assertCount(0, $typedConfigManager->create($dataDefinition, $value)->validate());
    }
    $this->assertCount(1, $typedConfigManager->create($dataDefinition, 'not-an-integer')->validate());

    $keys = [
      ['eca.condition.plugin.eca_route_match', 'request', 1],
      ['action.configuration.eca_token_load_route_param', 'request', 1],
      ['action.configuration.eca_write_log_message', 'severity', 6],
      ['action.configuration.eca_enqueue_task_delayed', 'delay_unit', 60],
    ];

    foreach ($keys as [$schemaType, $key, $integerValue]) {
      $definition = $typedConfigManager->getDefinition($schemaType);
      $this->assertSame('eca_integer_or_token', $definition['mapping'][$key]['type']);
      foreach ([$integerValue, '_eca_token'] as $value) {
        $data = [$key => $value];
        $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
        $typedData = $typedConfigManager->create($dataDefinition, $data);
        $this->assertInstanceOf(IntegerInterface::class, $typedData->get($key));
        $this->assertInstanceOf(StringInterface::class, $typedData->get($key));
        $violations = $typedData->validate();
        $this->assertCount(0, $violations, sprintf('%s:%s accepts %s.', $schemaType, $key, var_export($value, TRUE)));
      }
    }
  }

}
