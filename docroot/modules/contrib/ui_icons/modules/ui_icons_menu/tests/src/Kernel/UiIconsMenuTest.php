<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_menu\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Test the ui_icons_menu module.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[Group('ui_icons')]
#[CoversNothing]
class UiIconsMenuTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'menu_link_content',
    'link',
    'ui_icons',
    'ui_icons_menu',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
  }

  /**
   * Tests ui_icons_menu_entity_base_field_info_alter().
   */
  public function testEntityBaseFieldInfoAlter(): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('menu_link_content');
    $fields = MenuLinkContent::baseFieldDefinitions($entity_type);
    ui_icons_menu_entity_base_field_info_alter($fields, $entity_type);

    $this->assertArrayHasKey('link', $fields);

    $link_field = $fields['link'];
    $this->assertInstanceOf(BaseFieldDefinition::class, $link_field);

    $form_display_options = $link_field->getDisplayOptions('form');
    $this->assertIsArray($form_display_options);
    $this->assertArrayHasKey('type', $form_display_options);
    $this->assertContains($form_display_options['type'], ['icon_link_widget', 'icon_link_attributes_widget']);
  }

  /**
   * Data provider for ::testPreprocessMenu().
   */
  public static function iconDisplayDataProvider(): array {
    return [
      'icon only' => ['icon_only', ['icon']],
      'icon before' => ['before', ['icon', 'title']],
      'icon after' => ['after', ['title', 'icon']],
    ];
  }

  /**
   * Tests ui_icons_menu_preprocess_menu().
   */
  #[DataProvider('iconDisplayDataProvider')]
  public function testPreprocessMenu(?string $iconDisplay, array $expectedOrder): void {
    // Create a mock menu item.
    $title = 'Test Item';

    $menu_link = MenuLinkContent::create([
      'title' => $title,
      'link' => ['uri' => 'internal:/'],
    ]);
    $menu_link->save();

    $variables = [
      'items' => [
        [
          'url' => $menu_link->getUrlObject(),
          'title' => $menu_link->getTitle(),
          'below' => [],
        ],
      ],
    ];
    // Set icon options.
    $url = $variables['items'][0]['url'];
    $options = $url->getOptions();

    $options['icon'] = ['target_id' => 'test_minimal:foo'];
    if ($iconDisplay !== NULL) {
      $options['icon_display'] = $iconDisplay;
    }
    $url->setOptions($options);

    ui_icons_menu_preprocess_menu($variables);
    $actual = (string) $variables['items'][0]['title'];

    // Test the position of the dom element, the icon test is prefix by icon id,
    // let ignore HTML markup and compare only string.
    $result_dom = new \DOMDocument();
    $result_dom->loadHTML($actual);

    $actual = trim($result_dom->textContent);
    switch ($iconDisplay) {
      case 'icon_only':
        $this->assertEquals('foo:', $actual);
        break;

      case 'before':
        $this->assertStringStartsWith('foo:', $actual);
        $this->assertStringEndsWith('Test Item', $actual);
        break;

      case 'after':
        $this->assertStringStartsWith('Test Item', $actual);
        $this->assertStringEndsWith('foo:', $actual);
        break;
    }
  }

}
