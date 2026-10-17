<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_media\Kernel;

use Drupal\Tests\media\Kernel\MediaKernelTestBase;
use Drupal\ui_icons_media\Plugin\media\Source\Icon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the Icon class.
 *
 * @internal
 */
#[CoversClass(Icon::class)]
#[Group('ui_icons')]
#[Group('ui_icons_media')]
class IconSourceTest extends MediaKernelTestBase {

  /**
   * Icon pack from ui_icons_test module.
   */
  private const TEST_ICON_PACK_ID = 'test_path';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID = 'bar_group_1';

  /**
   * Icon path from ui_icons_test module.
   */
  private const TEST_ICON_PATH = '/icons/group/group_1/bar_group_1.png';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_media',
    'ui_icons_test',
  ];

  /**
   * Test the method ::getMetadata().
   */
  public function testGetMetadata(): void {
    /** @var \Drupal\Core\Extension\ModuleExtensionList $extensionList */
    $extensionList = $this->container->get('extension.list.module');

    $configuration = [
      'source_field' => 'field_media_ui_icon',
    ];
    $plugin = Icon::create($this->container, $configuration, 'ui_icon', [
      'default_thumbnail_filename' => 'no-thumbnail.png',
    ]);

    $fieldItems = $this->prophesize('\Drupal\ui_icons_field\Plugin\Field\FieldType\IconFieldItemList');
    $fieldItems->getValue()->willReturn([
      [
        'target_id' => $this::TEST_ICON_PACK_ID . ':' . $this::TEST_ICON_ID,
      ],
    ]);
    $media = $this->prophesize('\Drupal\media\MediaInterface');
    $media->get($configuration['source_field'])->willReturn($fieldItems->reveal());

    $expectations = [
      'thumbnail_uri' => Icon::THUMBNAIL_DIRECTORY . DIRECTORY_SEPARATOR . $this::TEST_ICON_PACK_ID . DIRECTORY_SEPARATOR . 'bar_group_1.png',
      Icon::METADATA_ATTRIBUTE_PACK_ID => $this::TEST_ICON_PACK_ID,
      Icon::METADATA_ATTRIBUTE_PACK_LABEL => 'Test path',
      Icon::METADATA_ATTRIBUTE_PACK_LICENSE => 'GPL-3.0-or-later',
      Icon::METADATA_ATTRIBUTE_ICON_ID => $this::TEST_ICON_ID,
      Icon::METADATA_ATTRIBUTE_ICON_FULL_ID => $this::TEST_ICON_PACK_ID . ':' . $this::TEST_ICON_ID,
      Icon::METADATA_ATTRIBUTE_ICON_GROUP => 'group_1',
      Icon::METADATA_ATTRIBUTE_ICON_SOURCE => '/' . $extensionList->getPath('ui_icons_test') . $this::TEST_ICON_PATH,
    ];
    foreach ($expectations as $attribute => $expectedValue) {
      $this->assertEquals($expectedValue, $plugin->getMetadata($media->reveal(), $attribute));
    }

    // Test with a remote icon to check that the thumbnail has not been created.
    $fieldItems = $this->prophesize('\Drupal\ui_icons_field\Plugin\Field\FieldType\IconFieldItemList');
    $fieldItems->getValue()->willReturn([
      [
        'target_id' => 'test_url_path:D10-logo',
      ],
    ]);
    $media = $this->prophesize('\Drupal\media\MediaInterface');
    $media->get($configuration['source_field'])->willReturn($fieldItems->reveal());

    $this->assertEquals('public://media-icons/generic/no-thumbnail.png', $plugin->getMetadata($media->reveal(), 'thumbnail_uri'));
  }

}
