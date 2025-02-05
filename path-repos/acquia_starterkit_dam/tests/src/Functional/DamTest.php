<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_starterkit_dam\Functional;

use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\NodeType;

/**
 * Tests creating a content type with a media field.
 *
 * @group my_module
 */
class DamTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'file',
    'media',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests creating a content type with a media field.
   */
  public function testDam() {
    // Create a media type for images.
    $media_type = MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
    ]);
    $media_type->save();

    // Create a content type (Page).
    $content_type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $content_type->save();

    // Add a field to the content type.
    $field_storage = [
      'field_name' => 'field_page_image',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
        'handler' => 'default',
      ],
    ];

    \Drupal::entityTypeManager()->getStorage('field_storage_config')->create($field_storage)->save();

    // Create the field instance for the content type.
    $field_instance = [
      'field_name' => 'field_page_image',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Image',
      'required' => FALSE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => ['image' => 'image'],
        ],
      ],
    ];

    \Drupal::entityTypeManager()->getStorage('field_config')->create($field_instance)->save();

    $this->drupalLogin($this->rootUser);
    $assert_session = $this->assertSession();
    // Verify the Page content type has media field with DAM asset.
    $this->drupalGet("admin/structure/types/manage/page/fields");
    $assert_session->pageTextContains('Media type: Image');

    $dir = realpath(__DIR__ . '/../../..');
    // Apply the DAM recipe.
    $this->applyRecipe($dir);

    // Verify the Page content type has media field with DAM asset.
    $this->drupalGet("admin/structure/types/manage/page/fields");
    $assert_session->pageTextContains('DAM - Image');
    $this->drupalGet("admin/structure/types/manage/page/fields/node.page.field_page_image");
    $assert_session->checkboxChecked('edit-settings-handler-settings-target-bundles-acquia-dam-image-asset');
  }

}
