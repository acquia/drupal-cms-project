<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Field storage settings form tests for custom field.
 *
 * @group custom_field
 */
class FieldStorageSettingsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_viewfield',
    'custom_field_test',
    'user',
    'system',
    'field',
    'field_ui',
    'text',
    'node',
    'path',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The custom fields on the test entity bundle.
   *
   * @var array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected array $fields = [];

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The bundle type.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The field name.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * The field array path.
   *
   * @var string
   */
  protected string $parentPath;

  /**
   * URL to field's storage configuration form.
   *
   * @var string
   */
  protected string $fieldStorageConfigUrl;

  /**
   * Entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldName = 'field_test';
    $this->bundle = 'custom_field_entity_test';
    $this->fieldStorageConfigUrl = '/admin/structure/types/manage/' . $this->bundle . '/fields/node.' . $this->bundle . '.' . $this->fieldName;
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->fields = $this->entityFieldManager->getFieldDefinitions('node', $this->bundle);

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->parentPath = 'field_storage[subform][settings][items]';
  }

  /**
   * Tests the settings form with stored configuration.
   */
  public function testFormSettings(): void {
    $field = $this->fields[$this->fieldName];
    $this->drupalGet($this->fieldStorageConfigUrl);
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $columns = $field->getSetting('columns');

    // Verify the clone settings field exists.
    $assert_session->elementExists('css', '[name="field_storage[subform][settings][clone]"]');

    // Iterate over each column stored in config to test form element.
    foreach ($columns as $name => $column) {
      $has_unsigned = FALSE;
      $type = $column['type'];
      $length_field = $page->find('css', "input[name='$this->parentPath[$name][length]']");
      $size_field = $page->find('css', "select[name='$this->parentPath[$name][size]']");
      $precision_field = $page->find('css', "input[name='$this->parentPath[$name][precision]']");
      $scale_field = $page->find('css', "input[name='$this->parentPath[$name][scale]']");
      $unsigned_field = $page->find('css', "input[name='$this->parentPath[$name][unsigned]']");
      $datetime_type_field = $page->find('css', "select[name='$this->parentPath[$name][datetime_type]']");
      $target_type_field = $page->find('css', "select[name='$this->parentPath[$name][target_type]']");
      $uri_scheme_field = $page->find('css', "input[name='$this->parentPath[$name][uri_scheme]']");

      // Verify the type field is present and required.
      $type_field = $page->find('css', "select[name='$this->parentPath[$name][type]']");
      $this->assertNotNull($type_field, 'Type field exists');
      $this->assertTrue($type_field->hasAttribute('required'), 'Type field is required.');
      $this->assertEquals($type, $type_field->getValue(), 'The configured data type is selected');

      // Check 'length' field.
      if (in_array($type, ['string', 'telephone'])) {
        $this->assertNotNull($length_field, 'Length field exists');
        $this->assertEquals($column['length'], $length_field->getValue(), 'The configured value equals the form value.');
      }
      else {
        $this->assertNull($length_field, sprintf('Length field should not exist for type "%s"', $type));
      }

      // Check 'size' field.
      if (in_array($type, ['integer', 'float'])) {
        $has_unsigned = TRUE;
        $this->assertNotNull($size_field, 'Size field exists');
        $this->assertOptionSelected("$this->parentPath[$name][size]", $column['size'], 'The configured size is selected.');
      }
      else {
        $this->assertNull($size_field, sprintf('Size field should not exist for type "%s"', $type));
      }

      // Check decimal specific fields.
      if ($type === 'decimal') {
        $has_unsigned = TRUE;
        $this->assertNotNull($precision_field, 'Precision field exists');
        $this->assertNotNull($scale_field, 'Scale field exists');
      }
      else {
        $this->assertNull($precision_field, sprintf('Precision field should not exist for type "%s"', $type));
        $this->assertNull($scale_field, sprintf('Scale field should not exist for type "%s"', $type));
      }

      // Check 'unsigned' field.
      if ($has_unsigned) {
        $this->assertNotNull($unsigned_field, 'Unsigned field exists');
        $this->assertTrue($unsigned_field->isChecked() === (bool) $unsigned_field->getValue(), 'The unsigned field is checked if the value is true.');
      }
      else {
        $this->assertNull($unsigned_field, sprintf('Unsigned field should not exist for type "%s"', $type));
      }

      // Check 'datetime_type' field.
      if (in_array($type, ['datetime', 'daterange'])) {
        $this->assertNotNull($datetime_type_field, 'The datetime_type field exists.');
        $this->assertOptionSelected("$this->parentPath[$name][datetime_type]", $column['datetime_type'], 'The configured datetime type is selected.');
      }
      else {
        $this->assertNull($datetime_type_field, sprintf('The datetime_type field should not exist for type "%s"', $type));
      }

      // Check 'target_type' field.
      if ($type === 'entity_reference') {
        $this->assertNotNull($target_type_field, 'The target_type field exists.');
      }
      else {
        $this->assertNull($target_type_field, sprintf('The target_type field should not exist for type "%s"', $type));
      }

      // Check 'uri_scheme' field.
      if (in_array($type, ['file', 'image'])) {
        $this->assertNotNull($uri_scheme_field, 'The uri_scheme field exists');
      }
      else {
        $this->assertNull($uri_scheme_field, sprintf('The uri_scheme field should not exist for type "%s"', $type));
      }
    }
  }

  /**
   * Tests the add/remove columns buttons with stored configuration.
   */
  public function testAddRemoveColumns(): void {
    $this->drupalGet($this->fieldStorageConfigUrl);
    $field = $this->fields[$this->fieldName];
    $columns = $field->getSetting('columns');
    $page = $this->getSession()->getPage();
    // Remove elements in descending order until getting to the last one.
    $column_count = count($columns);
    foreach ($columns as $name => $column) {
      $button_id = "remove_$name";
      $this->assertSession()->waitForElementVisible('css', "[name='$button_id']", 5000);
      $remove_button = $page->find('css', "[name='$button_id']");
      if ($column_count === 1) {
        $this->assertNull($remove_button, 'Remove button exists');
      }
      else {
        $remove_button->click();
        $column_count--;
        // Wait for the AJAX request to complete and the element to disappear.
        $this->assertSession()->assertWaitOnAjaxRequest();
      }
      $this->assertSession()->elementNotExists('css', "[name='$button_id']");
    }

    // Count remaining columns.
    $settings_element = $page->find('css', '[data-drupal-selector="edit-field-storage-subform-settings-items"]');
    $this->assertNotNull($settings_element, 'Settings element not found.');
    $details_children = $settings_element->findAll('css', 'details');
    $count = count($details_children);
    $this->assertEquals(1, $count, 'Remaining column count matches expected.');

    // Click the Add another button and verify the new element exists.
    $add_button = $page->findButton('Add sub-field');
    $this->assertNotNull($add_button, 'Add sub-field button exists');
    $add_button->click();

    // Wait for the second <details> element to become visible.
    $this->assertSession()->waitForElementVisible('css', '#edit-field-storage-subform-settings-items details:nth-of-type(2)', 5000);
    $updated_details_children = $settings_element->findAll('css', 'details');
    $new_count = count($updated_details_children);
    $this->assertEquals(2, $new_count, 'New column count matches expected.');
  }

  /**
   * Tests cloning field settings from another field.
   */
  public function testCloneSettings(): void {
    $field_copy = $this->fields[$this->fieldName];
    $field_copy_columns = $field_copy->getFieldStorageDefinition()->getColumns();

    // Create a generic custom field for validation.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_custom_generic',
      'entity_type' => 'node',
      'type' => 'custom',
    ]);
    $field_storage->save();

    $field_instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Generic custom field',
    ]);
    $field_instance->save();
    $article_config_url = '/admin/structure/types/manage/article/fields/node.article.field_custom_generic';

    // Set the article's form display.
    $this->formDisplay = EntityFormDisplay::load('node.article.default');

    if (!$this->formDisplay) {
      EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ])->save();
      $this->formDisplay = EntityFormDisplay::load('node.article.default');
    }
    $this->formDisplay->setComponent('field_custom_generic', [
      'type' => 'custom_stacked',
    ])->save();

    $this->drupalGet($article_config_url);
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Verify the clone settings field exists.
    $assert_session->elementExists('css', '[name="field_storage[subform][settings][clone]"]');
    $clone_field = $page->findField('field_storage[subform][settings][clone]');
    $option_value = 'node.' . $this->bundle . '.' . $this->fieldName;
    $clone_field->setValue($option_value);
    $this->assertEquals($option_value, $clone_field->getValue(), 'Clone field is set to the correct value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContainsOnce('The selected custom field field settings will be cloned. Any existing settings for this field will be overwritten. Field widget and formatter settings will not be cloned.');

    // Save the form.
    $save_button = $page->findButton('Save settings');
    $this->assertNotNull($save_button, 'Save settings button exists');
    $save_button->press();
    $this->assertSession()->assert($this->assertSession()->waitForText("Saved Generic custom field configuration."), 'text not found');

    // Load the cloned storage and see if they match the source.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_custom_generic');
    $columns = $field_storage->getColumns();
    $this->assertEquals($field_copy_columns, $columns, 'The cloned columns match the source columns.');
    $this->drupalGet($article_config_url);
  }

  /**
   * Asserts that a select field has a selected option.
   *
   * @param string $id
   *   ID of select field to assert.
   * @param string $option
   *   Option to assert.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   */
  protected function assertOptionSelected(string $id, string $option, string $message = ''): void {
    $select = $this->getSession()->getPage()->findField($id);
    $this->assertNotNull($select, "Select field $id exists");
    $this->assertEquals($option, $select->getValue(), $message ?: "Option $option for field $id is selected.");
  }

}
