<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_media\Functional;

use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldConfigStorage;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field\FieldStorageConfigStorage;
use Drupal\Tests\media\Functional\MediaFunctionalTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the custom media source plugin.
 *
 * @internal
 */
#[Group('ui_icons')]
class MediaSourceTest extends MediaFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_icons_media',
  ];

  /**
   * The field storage storage.
   *
   * @var \Drupal\field\FieldStorageConfigStorage
   */
  protected FieldStorageConfigStorage $fieldStorageStorage;

  /**
   * The field storage.
   *
   * @var \Drupal\field\FieldConfigStorage
   */
  protected FieldConfigStorage $fieldStorage;

  /**
   * Factorized data to test.
   *
   * @var array[]
   */
  protected static $testedPlugins = [
    'ui_icon' => [
      'field_name' => 'field_media_ui_icon',
      'field_type' => 'ui_icon',
      'label' => 'Icon',
    ],
  ];

  /**
   * Tests icon media source plugin.
   */
  public function testIconSourcePlugin(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->fieldStorageStorage = $entity_type_manager->getStorage('field_storage_config');
    $this->fieldStorage = $entity_type_manager->getStorage('field_config');

    $pluginId = 'ui_icon';
    $fieldName = 'field_media_ui_icon';
    $fieldType = 'ui_icon';
    $fieldLabel = 'Icon';

    $media_type = $this->createMediaType($pluginId);
    $media_type_id = $media_type->id();

    // Test that the field had been created.
    $field = $this->fieldStorageStorage->load("media.{$fieldName}");
    $this->assertTrue($field instanceof FieldStorageConfigInterface);
    $this->assertEquals($fieldType, $field->getType());

    $field_config = $this->fieldStorage->load("media.{$media_type_id}.{$fieldName}");
    $this->assertTrue($field_config instanceof FieldConfigInterface);
    $this->assertEquals($fieldLabel, $field_config->label());
    $this->assertEquals($fieldType, $field_config->get('field_type'));
    $this->assertEquals($fieldName, $field_config->get('field_name'));

    // Ensure source field deletion is not possible.
    $this->drupalGet("admin/structure/media/manage/{$media_type_id}/fields/media.{$media_type_id}.{$fieldName}/delete");
    $this->assertSession()->statusCodeEquals(403);
  }

}
