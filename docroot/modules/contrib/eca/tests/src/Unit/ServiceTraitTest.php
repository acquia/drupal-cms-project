<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Tests\UnitTestCase;
use Drupal\eca\Service\ServiceTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the service trait.
 */
#[Group('eca')]
class ServiceTraitTest extends UnitTestCase {

  use ServiceTrait;

  /**
   * The module extension manager.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $extensions;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->extensions = $this->getExtensions();
  }

  /**
   * Tests the sort of plugins.
   */
  public function testSortPlugins(): void {
    $plugins = [];
    foreach ([
      'testPluginB',
      'testPluginC',
      'testPluginA',
      'testPluginC',
    ] as $label) {
      $plugins[] = $this->getPluginMock($label);
    }
    $this->sortPlugins($plugins, $this->extensions);
    foreach ([
      'testPluginA',
      'testPluginB',
      'testPluginC',
      'testPluginC',
    ] as $key => $label) {
      $this->assertEquals($label, $plugins[$key]->getPluginDefinition()['label']);
    }
  }

  /**
   * Gets plugin mocks by the given label.
   *
   * @param string $label
   *   The plugin label.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface
   *   The stubbed plugin.
   */
  private function getPluginMock(string $label): PluginInspectionInterface {
    $mockObject = $this->createStub(PluginInspectionInterface::class);
    $mockObject->method('getPluginDefinition')->willReturn([
      'label' => $label,
    ]);
    return $mockObject;
  }

  /**
   * Gets the extension manager mock.
   *
   * @return \Drupal\Core\Extension\ModuleExtensionList
   *   The mocked extension manager.
   */
  private function getExtensions(): ModuleExtensionList {
    $mockObject = $this->createStub(ModuleExtensionList::class);
    $mockObject->method('getName')->willReturn('eca');
    return $mockObject;
  }

  /**
   * Tests the method fieldLabel with NULL value.
   */
  public function testFieldLabelWithNull(): void {
    $this->assertEquals('A test key',
      self::convertKeyToLabel('a_test_key'));
  }

}
