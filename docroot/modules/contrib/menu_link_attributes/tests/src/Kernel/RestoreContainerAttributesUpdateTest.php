<?php

namespace Drupal\Tests\menu_link_attributes\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Tests menu_link_attributes_post_update_restore_container_attributes().
 *
 * @group menu_link_attributes
 */
class RestoreContainerAttributesUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_link_attributes',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['menu_link_attributes']);
    \Drupal::moduleHandler()->loadInclude('menu_link_attributes', 'php', 'menu_link_attributes.post_update');
  }

  /**
   * Tests that container attributes still stored on links are restored.
   */
  public function testRestoreContainerAttributes(): void {
    $config = $this->config('menu_link_attributes.config');
    $attributes = $config->get('attributes');
    // Simulate what update 8004 removed.
    unset($attributes['container_class']);
    $attributes['container_id'] = ['label' => 'Custom label'];
    $config->set('attributes', $attributes)->save();

    MenuLinkContent::create([
      'title' => 'Container link',
      'menu_name' => 'main',
      'link' => [
        'uri' => 'internal:/',
        'options' => [
          'container_attributes' => [
            'class' => ['foo'],
            'id' => 'bar',
            'data-baz' => 'baz',
            'onmouseover' => 'alert(1)',
          ],
        ],
      ],
    ])->save();

    $sandbox = [];
    do {
      menu_link_attributes_post_update_restore_container_attributes($sandbox);
    } while ($sandbox['#finished'] < 1);

    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    $this->assertSame('Container class(es)', $attributes['container_class']['label']);
    $this->assertSame([], $attributes['container_data-baz']);
    // Existing definitions are kept as they are.
    $this->assertSame(['label' => 'Custom label'], $attributes['container_id']);
    $this->assertArrayNotHasKey('container_onmouseover', $attributes);
    $this->assertArrayHasKey('class', $attributes);
  }

}
