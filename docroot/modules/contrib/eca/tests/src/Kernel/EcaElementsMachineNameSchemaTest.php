<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ECA elements machine name configuration schema validation.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class EcaElementsMachineNameSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_render',
    'modeler_api',
  ];

  /**
   * Tests elements machine name values against the action schema.
   *
   * @param string $value
   *   The value to validate.
   * @param bool $valid
   *   Whether the value is expected to be valid.
   */
  #[DataProvider('machineNameProvider')]
  public function testMachineName(string $value, bool $valid): void {
    $typedConfigManager = $this->container->get('config.typed');
    $definition = $typedConfigManager->getDefinition('action.configuration.eca_render_markup');
    $data = ['name' => $value];
    $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
    $element = $typedConfigManager->create($dataDefinition, $data);

    $violations = $element->validate();
    $this->assertSame($valid ? 0 : 1, $violations->count());
  }

  /**
   * Provides elements machine names and their expected validity.
   *
   * @return array<string, array{string, bool}>
   *   The test cases.
   */
  public static function machineNameProvider(): array {
    return [
      'machine name' => ['details_title', TRUE],
      'nested machine name' => ['details][title', TRUE],
      'tokenized machine name' => ['details][[token:value]', TRUE],
      'empty machine name' => ['', TRUE],
      'spaces' => ['details title', FALSE],
      'period' => ['details.title', FALSE],
    ];
  }

}
