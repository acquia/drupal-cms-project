<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Component;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for ECA-extended Token replacement behavior.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class EcaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'eca',
    'eca_base',
    'eca_ui',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
  }

  /**
   * Tests an invalid token field.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testInvalidTokenField(): void {
    $owner = \Drupal::service('plugin.manager.modeler_api.model_owner')->createInstance('eca');
    $component = new Component(
      $owner,
      'id',
      Api::COMPONENT_TYPE_ELEMENT,
      'eca_count',
      'Count',
      [
        'list_token' => 'list',
        'token_name' => '[test]',
      ],
    );
    $this->assertEquals(['action "Count" (id): This field requires a token name, not a token; please remove the brackets.'], $component->validate());
  }

  /**
   * Tests a valid token field.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testValidTokenField(): void {
    $owner = \Drupal::service('plugin.manager.modeler_api.model_owner')->createInstance('eca');
    $component = new Component(
      $owner,
      'id',
      Api::COMPONENT_TYPE_ELEMENT,
      'eca_count',
      'Count',
      [
        'list_token' => 'list',
        'token_name' => 'test',
      ],
    );
    $this->assertEmpty($component->validate());
  }

}
