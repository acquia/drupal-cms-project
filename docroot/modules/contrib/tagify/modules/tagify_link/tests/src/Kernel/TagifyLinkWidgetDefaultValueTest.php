<?php

namespace Drupal\Tests\tagify_link\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\tagify\Traits\TagifyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests TagifyLinkWidget::buildDefaultValue().
 *
 * @group tagify_link
 */
class TagifyLinkWidgetDefaultValueTest extends KernelTestBase {

  use TagifyTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'link',
    'node',
    'system',
    'tagify',
    'tagify_link',
    'text',
    'user',
  ];

  /**
   * The node referenced by the link under test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $target;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('field_link', 'node', 'article', 'link', [], [], 'tagify_link_widget', [
      'match_operator' => 'CONTAINS',
      'match_limit' => 20,
      'show_info_label' => 1,
      'info_label' => '[node:title]',
    ]);

    // Admin user so the 'view label' access check passes.
    $this->setUpCurrentUser([], [], TRUE);

    $this->target = Node::create(['type' => 'article', 'title' => 'Drupal']);
    $this->target->save();
  }

  /**
   * Invokes the protected buildDefaultValue() for a given stored URI.
   *
   * @param string $uri
   *   The stored link URI.
   *
   * @return string|null
   *   The JSON descriptor, or NULL.
   */
  protected function buildFor(string $uri): ?string {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Host',
      'field_link' => ['uri' => $uri],
    ]);
    $widget = \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->getRenderer('field_link');

    $method = new \ReflectionMethod($widget, 'buildDefaultValue');
    $method->setAccessible(TRUE);
    return $method->invoke($widget, $node->get('field_link'), 0);
  }

  /**
   * An internal entity link resolves to a descriptor with badges.
   */
  public function testEntityLink(): void {
    $json = $this->buildFor('entity:node/' . $this->target->id());
    $this->assertIsString($json);

    $data = json_decode($json, TRUE);
    $this->assertCount(1, $data);
    $this->assertSame((string) $this->target->id(), (string) $data[0]['entity_id']);
    $this->assertSame('Drupal', $data[0]['label']);
    // info_label is token-replaced from [node:title].
    $this->assertSame('Drupal', $data[0]['info_label']);
  }

  /**
   * The relaxed regex still matches a URI with a trailing fragment/query.
   */
  public function testEntityLinkWithTrailingFragment(): void {
    $json = $this->buildFor('entity:node/' . $this->target->id() . '#section');
    $this->assertIsString($json);
    $data = json_decode($json, TRUE);
    $this->assertSame((string) $this->target->id(), (string) $data[0]['entity_id']);
  }

  /**
   * Non-entity link values yield no descriptor.
   *
   * @dataProvider providerNonEntityUris
   */
  public function testNonEntityUri(string $uri): void {
    $this->assertNull($this->buildFor($uri));
  }

  /**
   * Data provider for testNonEntityUri().
   *
   * @return array
   *   Sets of [uri].
   */
  public static function providerNonEntityUris(): array {
    return [
      'external url' => ['https://example.com'],
      'special nolink' => ['route:<nolink>'],
      'empty' => [''],
      'nonexistent node' => ['entity:node/999999'],
    ];
  }

}
