<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Kernel\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayString;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests token resolution in the 'array_string' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayString
 * @runTestsInSeparateProcesses
 */
#[CoversClass(PropWidgetArrayString::class)]
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
class PropWidgetArrayStringTest extends KernelTestBase {

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
   * @var \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayString
   */
  protected PropWidgetArrayString $plugin;

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

    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('name', 'Test Site')
      ->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->node = Node::create([
      'type' => 'page',
      'title' => 'My Test Node',
      'status' => 1,
    ]);
    $this->node->save();

    $this->plugin = $this->container
      ->get('plugin.manager.custom_field_component_prop_widget')
      ->createInstance('array_string');
  }

  /**
   * Tests that getPropValue() returns plain strings unchanged.
   */
  public function testGetPropValueReturnsPlainStringsUnchanged(): void {
    $result = $this->plugin->getPropValue(['foo', 'bar', 'baz'], []);
    $this->assertSame(['foo', 'bar', 'baz'], $result);
  }

  /**
   * Tests that getPropValue() resolves tokens in each array item.
   */
  public function testGetPropValueResolvesTokensInEachItem(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue([
      'plain string',
      '[node:title]',
      'Another plain string',
    ], $context);
    $this->assertIsArray($result);
    $this->assertCount(3, $result);
    $this->assertSame('plain string', $result[0]);
    $this->assertSame('My Test Node', $result[1]);
    $this->assertSame('Another plain string', $result[2]);
  }

  /**
   * Tests that getPropValue() filters items that resolve to empty.
   */
  public function testGetPropValueFiltersItemsThatResolveToEmpty(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue([
      'valid string',
      '[node:nonexistent-field]',
    ], $context);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertSame('valid string', $result[0]);
  }

  /**
   * Tests that getPropValue() returns NULL when all items resolve to empty.
   */
  public function testGetPropValueReturnsNullWhenAllItemsResolveToEmpty(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue([
      '[node:nonexistent-field]',
      '[node:another-nonexistent]',
    ], $context);
    $this->assertNull($result);
  }

  /**
   * Tests that getPropValue() resolves mixed static and token content per item.
   */
  public function testGetPropValueResolvesMixedContentPerItem(): void {
    $context = [
      'entity_type' => 'node',
      'entity' => $this->node,
    ];
    $result = $this->plugin->getPropValue([
      'Title: [node:title]',
      'Static item',
    ], $context);
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertSame('Title: My Test Node', $result[0]);
    $this->assertSame('Static item', $result[1]);
  }

  /**
   * Tests that getPropValue() resolves site tokens without entity context.
   */
  public function testGetPropValueResolvesSiteTokensWithoutEntity(): void {
    $result = $this->plugin->getPropValue(['[site:name]', 'static'], []);
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertSame('Test Site', $result[0]);
    $this->assertSame('static', $result[1]);
  }

  /**
   * Tests that getPropValue() returns NULL for non-array input.
   */
  public function testGetPropValueReturnsNullForNonArray(): void {
    $this->assertNull($this->plugin->getPropValue(NULL, []));
    $this->assertNull($this->plugin->getPropValue('string', []));
    $this->assertNull($this->plugin->getPropValue(42, []));
  }

  /**
   * Tests that getPropValue() returns NULL for empty array.
   */
  public function testGetPropValueReturnsNullForEmptyArray(): void {
    $this->assertNull($this->plugin->getPropValue([], []));
  }

  /**
   * Tests that getPropValue() re-indexes after filtering.
   */
  public function testGetPropValueReindexesAfterFiltering(): void {
    $result = $this->plugin->getPropValue(['foo', '', 'bar'], []);
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertArrayHasKey(0, $result);
    $this->assertArrayHasKey(1, $result);
    $this->assertSame('foo', $result[0]);
    $this->assertSame('bar', $result[1]);
  }

}
