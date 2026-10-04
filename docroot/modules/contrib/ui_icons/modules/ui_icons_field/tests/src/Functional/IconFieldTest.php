<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Functional;

use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test the UI icons field features.
 *
 * @group ui_icons
 */
class IconFieldTest extends BrowserTestBase {
  /**
   * Icon pack from ui_icons_test module.
   */
  private const TEST_ICON_PACK_ID = 'test_path';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID = 'foo';

  /**
   * Icon filename from ui_icons_test module.
   */
  private const TEST_ICON_FILENAME = 'foo.png';

  /**
   * Icon class from ui_icons_test module.
   */
  private const TEST_ICON_CLASS = '.icon-foo';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The field name.
   *
   * @var string
   */
  private $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldName = 'field_icon';

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'administer nodes',
      'administer node display',
      'administer node fields',
      'administer node form display',
      'view the administration theme',
      'create article content',
    ]));
  }

  /**
   * Test field icon create, save and display.
   */
  public function testIconFieldSave(): void {
    $label = 'Icon test';
    $icon_full_id = IconDefinition::createIconId(self::TEST_ICON_PACK_ID, self::TEST_ICON_ID);

    // Create a field and storage for checking.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => 'ui_icon',
    ])->save();
    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => TRUE,
      'label' => $label,
    ]);
    $field_config->save();

    $assert_session = $this->assertSession();

    // Check if field settings are available.
    $this->drupalGet('/admin/structure/types/manage/article/fields/node.article.' . $this->fieldName);
    $assert_session->fieldExists(sprintf('settings[allowed_icon_pack][%s]', self:: TEST_ICON_PACK_ID));

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent($this->fieldName, [
        'type' => 'icon_widget',
      ])
      ->save();

    $display_repository->getViewDisplay('node', 'article')
      ->setComponent($this->fieldName, [
        'type' => 'icon_formatter',
        'weight' => 1,
      ])
      ->save();

    // Check if widget settings are available.
    $this->drupalGet('/admin/structure/types/manage/article/form-display');
    $assert_session->statusCodeEquals(200);
    $assert_session->optionExists('fields[' . $this->fieldName . '][type]', 'icon_widget');

    // Create a new article node.
    $this->drupalGet('/node/add/article');
    $assert_session->statusCodeEquals(200);

    // Check if the icon field is present.
    $assert_session->fieldExists($this->fieldName . '[0][value][icon_id]');

    // Try to save the node without selecting an icon.
    $edit = [
      'title[0][value]' => 'Test Article',
    ];
    $this->drupalGet('/node/add/article');
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains(sprintf('%s field is required.', $label));

    // Select an icon and save the node.
    $edit = [
      'title[0][value]' => 'Test Article',
      $this->fieldName . '[0][value][icon_id]' => $icon_full_id,
    ];
    $this->drupalGet('/node/add/article');
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains('Article Test Article has been created.');
    $assert_session->elementExists('css', self::TEST_ICON_CLASS);
    $assert_session->elementExists('css', sprintf(".icon[src$='%s']", self::TEST_ICON_FILENAME));
  }

}
