<?php

namespace Drupal\Tests\menu_link_attributes\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Tests the migration of attribute names with underscores to hyphens.
 *
 * @group menu_link_attributes
 */
class MigrateUnderscoreAttributesUpdateTest extends KernelTestBase {

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
    \Drupal::moduleHandler()->loadInclude('menu_link_attributes', 'install');
    \Drupal::moduleHandler()->loadInclude('menu_link_attributes', 'php', 'menu_link_attributes.post_update');
  }

  /**
   * Tests that update 8004 renames instead of removing such attributes.
   */
  public function testUpdate8004RenamesAttributes(): void {
    $config = $this->config('menu_link_attributes.config');
    $attributes = $config->get('attributes');
    $attributes['menu_color'] = [
      'label' => 'Menu color',
      'options' => ['blue' => 'Blue'],
      'container' => TRUE,
    ];
    $attributes['container_data_foo'] = ['label' => 'Foo'];
    $attributes['on_click'] = [];
    $config->set('attributes', $attributes)->save();

    $message = (string) menu_link_attributes_update_8004();
    $this->assertStringContainsString('menu_color → menu-color, container_data_foo → container_data-foo', $message);
    $this->assertStringContainsString('Update front-end code', $message);

    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    $this->assertSame([
      'label' => 'Menu color',
      'options' => ['blue' => 'Blue'],
      'container' => TRUE,
    ], $attributes['menu-color']);
    $this->assertSame(['label' => 'Foo'], $attributes['container_data-foo']);
    $this->assertArrayNotHasKey('menu_color', $attributes);
    $this->assertArrayNotHasKey('container_data_foo', $attributes);
    $this->assertArrayNotHasKey('on_click', $attributes);
    $this->assertArrayNotHasKey('on-click', $attributes);
  }

  /**
   * Tests that stored values are renamed and definitions restored.
   */
  public function testMigrateUnderscoreAttributes(): void {
    $config = $this->config('menu_link_attributes.config');
    $attributes = $config->get('attributes');
    // Renamed by the fixed update 8004.
    $attributes['menu-color'] = ['label' => 'Menu color', 'container' => TRUE];
    $config->set('attributes', $attributes)->save();

    $menu_link = MenuLinkContent::create([
      'title' => 'Link',
      'menu_name' => 'main',
      'link' => [
        'uri' => 'internal:/',
        'options' => [
          'attributes' => [
            'data_foo' => 'foo',
            'target' => '_blank',
          ],
          'container_attributes' => [
            'menu_color' => 'blue',
            'data_bar' => 'bar',
            'data_baz' => 'old',
            'data-baz' => 'new',
            'on_click' => 'alert(1)',
          ],
        ],
      ],
    ]);
    $menu_link->save();

    $sandbox = [];
    do {
      $message = (string) menu_link_attributes_post_update_migrate_underscore_attributes($sandbox);
    } while ($sandbox['#finished'] < 1);
    $this->assertStringContainsString('in menu links: data_foo → data-foo, menu_color → menu-color, data_bar → data-bar, data_baz → data-baz. Update front-end code', $message);

    $options = MenuLinkContent::load($menu_link->id())->link->first()->options;
    $this->assertSame([
      'data-foo' => 'foo',
      'target' => '_blank',
    ], $options['attributes']);
    $this->assertSame([
      'menu-color' => 'blue',
      'data-bar' => 'bar',
      'data-baz' => 'new',
      'on_click' => 'alert(1)',
    ], $options['container_attributes']);

    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    // Existing definitions are kept as they are.
    $this->assertSame(['label' => 'Menu color', 'container' => TRUE], $attributes['menu-color']);
    $this->assertArrayNotHasKey('container_menu-color', $attributes);
    $this->assertSame([], $attributes['data-foo']);
    $this->assertSame([], $attributes['container_data-bar']);
    $this->assertSame([], $attributes['container_data-baz']);
    $this->assertArrayNotHasKey('container_on-click', $attributes);
  }

  /**
   * Tests a direct update from 8.x-1.7, running all updates in core's order.
   */
  public function testDirectUpdate(): void {
    $config = $this->config('menu_link_attributes.config');
    $attributes = $config->get('attributes');
    $attributes['menu_color'] = ['label' => 'Menu color', 'container' => TRUE];
    $attributes['highlight'] = ['label' => 'Highlight', 'container' => TRUE];
    $config->set('attributes', $attributes)->save();

    $menu_link = MenuLinkContent::create([
      'title' => 'Link',
      'menu_name' => 'main',
      'link' => [
        'uri' => 'internal:/',
        'options' => [
          'container_attributes' => [
            'menu_color' => 'blue',
            'highlight' => 'yes',
          ],
        ],
      ],
    ]);
    $menu_link->save();

    menu_link_attributes_update_8004();
    $post_updates = [
      'menu_link_attributes_post_update_migrate_underscore_attributes',
      'menu_link_attributes_post_update_restore_container_attributes',
    ];
    $sorted_post_updates = $post_updates;
    sort($sorted_post_updates);
    $this->assertSame($post_updates, $sorted_post_updates);
    foreach ($post_updates as $post_update) {
      $sandbox = [];
      do {
        $post_update($sandbox);
      } while ($sandbox['#finished'] < 1);
    }

    $options = MenuLinkContent::load($menu_link->id())->link->first()->options;
    $this->assertSame(['menu-color' => 'blue', 'highlight' => 'yes'], $options['container_attributes']);

    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    $this->assertSame(['label' => 'Menu color', 'container' => TRUE], $attributes['menu-color']);
    $this->assertSame(['label' => 'Highlight', 'container' => TRUE], $attributes['highlight']);
    $this->assertArrayNotHasKey('container_menu-color', $attributes);
    $this->assertArrayNotHasKey('container_highlight', $attributes);
  }

  /**
   * Tests that duplicates added by the 8.x-1.9 restore are removed.
   */
  public function testRemoveDuplicateContainerAttributes(): void {
    $config = $this->config('menu_link_attributes.config');
    $attributes = $config->get('attributes');
    $attributes['highlight'] = ['label' => 'Highlight', 'container' => TRUE];
    $attributes['container_highlight'] = [];
    $attributes['badge'] = ['label' => 'Badge', 'container' => TRUE];
    $attributes['container_badge'] = ['label' => 'Edited by hand'];
    $config->set('attributes', $attributes)->save();

    $sandbox = [];
    do {
      menu_link_attributes_post_update_migrate_underscore_attributes($sandbox);
    } while ($sandbox['#finished'] < 1);

    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    $this->assertArrayHasKey('highlight', $attributes);
    $this->assertArrayNotHasKey('container_highlight', $attributes);
    // Definitions that are not empty are kept.
    $this->assertSame(['label' => 'Edited by hand'], $attributes['container_badge']);
    $this->assertArrayHasKey('container_class', $attributes);
  }

}
