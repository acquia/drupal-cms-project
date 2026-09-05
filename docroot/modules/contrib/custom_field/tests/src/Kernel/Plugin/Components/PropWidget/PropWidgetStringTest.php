<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Kernel\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetString;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests token resolution in the 'string' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetString
 * @runTestsInSeparateProcesses
 */
#[CoversClass(PropWidgetString::class)]
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
class PropWidgetStringTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'token',
    'node',
    'user',
    'system',
    'field',
    'text',
    'filter',
  ];

  /**
   * The plugin under test.
   *
   * @var \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetString
   */
  protected PropWidgetString $plugin;

  /**
   * A test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'filter']);
    \Drupal::configFactory()->getEditable('system.site')->set('name', 'Test Site')->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->node = Node::create([
      'type' => 'page',
      'title' => 'My Test Node',
      'status' => 1,
    ]);
    $this->node->save();

    $this->plugin = $this->container
      ->get('plugin.manager.custom_field_component_prop_widget')
      ->createInstance('string');
  }

  /**
   * Tests that getPropValue() returns plain strings unchanged.
   */
  public function testGetPropValueReturnsPlainStringUnchanged(): void {
    $result = $this->plugin->getPropValue('plain string', []);
    $this->assertSame('plain string', $result);
  }

  /**
   * Tests that getPropValue() resolves node tokens correctly.
   */
  public function testGetPropValueResolvesNodeTokens(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue('[node:title]', $context);
    $this->assertSame('My Test Node', $result);
  }

  /**
   * Tests that getPropValue() resolves mixed static and token content.
   */
  public function testGetPropValueResolvesMixedContent(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue('Title: [node:title]', $context);
    $this->assertSame('Title: My Test Node', $result);
  }

  /**
   * Tests that getPropValue() returns NULL when token resolves to empty.
   */
  public function testGetPropValueReturnsNullWhenTokenResolvesToEmpty(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue('[node:nonexistent-field]', $context);
    $this->assertNull($result);
  }

  /**
   * Tests that getPropValue() returns NULL for empty strings.
   */
  public function testGetPropValueReturnsNullForEmptyString(): void {
    $result = $this->plugin->getPropValue('', []);
    $this->assertNull($result);
  }

  /**
   * Tests that getPropValue() returns NULL for non-string values.
   */
  public function testGetPropValueReturnsNullForNonStringValues(): void {
    $this->assertNull($this->plugin->getPropValue(NULL, []));
    $this->assertNull($this->plugin->getPropValue(42, []));
    $this->assertNull($this->plugin->getPropValue([], []));
  }

  /**
   * Tests that getPropValue() resolves site tokens without entity context.
   */
  public function testGetPropValueResolvesSiteTokensWithoutEntity(): void {
    $site_name = \Drupal::config('system.site')->get('name');
    $result = $this->plugin->getPropValue('[site:name]', []);
    $this->assertSame($site_name, $result);
  }

}
