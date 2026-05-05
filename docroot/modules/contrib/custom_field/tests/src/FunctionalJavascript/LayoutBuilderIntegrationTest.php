<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

use Drupal\Tests\layout_builder\FunctionalJavascript\InlineBlockTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Layout builder integration tests for custom field.
 *
 * @group custom_field
 */
class LayoutBuilderIntegrationTest extends InlineBlockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'block_content',
    'layout_builder',
    'block',
    'node',
    'field_ui',
    'contextual',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a generic custom field for validation.
    FieldStorageConfig::create(
      [
        'field_name' => 'field_test',
        'entity_type' => 'block_content',
        'type' => 'custom',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'settings' => [
          'columns' => [
            'string_1' => [
              'name' => 'string_1',
              'type' => 'string',
              'length' => 255,
            ],
            'string_2' => [
              'name' => 'string_2',
              'type' => 'string',
              'length' => 255,
            ],
          ],
        ],
      ]
    )->save();

    FieldConfig::create(
      [
        'entity_type' => 'block_content',
        'field_name' => 'field_test',
        'bundle' => 'basic',
        'settings' => [
          'field_settings' => [
            'string_1' => [
              'check_empty' => FALSE,
              'description' => '',
              'description_display' => 'after',
            ],
            'string_2' => [
              'check_empty' => TRUE,
              'description' => '',
              'description_display' => 'after',
            ],
          ],
        ],
      ]
    )->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Turn on layout builder.
    $display_repository
      ->getViewDisplay('node', 'bundle_with_section_field')
      ->enableLayoutBuilder()
      ->save();

    // Show the custom field on the block view and form displays.
    // Assign widget settings for the default form mode.
    $display_repository->getFormDisplay('block_content', 'basic')
      ->setComponent('field_test', [
        'type' => 'custom_stacked',
      ])
      ->save();

    // Assign display settings for default view mode.
    $display_repository->getViewDisplay('block_content', 'basic')
      ->setComponent('field_test', [
        'label' => 'hidden',
        'type' => 'custom_formatter',
      ])
      ->save();

    // Resize the window to prevent 'element is not clickable' red herrings.
    $this->getSession()->resizeWindow(1920, 2000);
  }

  /**
   * Test case for layout builder integration.
   */
  public function testLayoutBuilderIntegration(): void {

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));

    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');

    $this->clickLink('Manage layout');

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Remove all existing blocks.
    $existing_block = $page->find('css', '.layout-builder-block');
    while ($existing_block) {
      $this->clickContextualLink('.layout-builder-block', 'Remove');
      $assert_session->assertWaitOnAjaxRequest();
      $page->pressButton('Remove');
      $assert_session->assertWaitOnAjaxRequest();
      $existing_block = $page->find('css', '.layout-builder-block');
    }

    $page->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForLink('Create content block'));
    $this->clickLink('Create content block');
    $assert_session->assertWaitOnAjaxRequest();
    $textarea = $assert_session->waitForElement('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue('Custom field test');
    $textarea->setValue('Custom field test body');

    // Fill out some custom field data.
    $custom_field_data = [
      ['string_1' => 'Delta 0, string 1', 'string_2' => 'Delta 0, string 2'],
      ['string_1' => 'Delta 1, string 1', 'string_2' => 'Delta 1, string 2'],
      ['string_1' => 'Delta 2, string 1', 'string_2' => 'Delta 2, string 2'],
    ];

    foreach ($custom_field_data as $delta => $field_data) {
      foreach ($field_data as $field_name => $string_value) {
        $assert_session->waitForElement('css', "[name='settings[block_form][field_test][$delta][$field_name]']")
          ->setValue($string_value);
      }
      if ($delta < count($custom_field_data) - 1) {
        // Click the 'Add another', button.
        $page->pressButton('Add another item');
      }
    }

    // Add the block to the page.
    $page->pressButton('Add block');

    $assert_session->waitForElement('css', '.customfield');

    // Make sure the page displays all item data.
    foreach ($custom_field_data as $delta => $field_data) {
      foreach ($field_data as $string_value) {
        $assert_session->pageTextContainsOnce($string_value);
      }
    }

    // Edit the block and ensure the edit form values are correct.
    $this->clickContextualLink('.layout-builder-block', 'Configure');
    foreach ($custom_field_data as $delta => $field_data) {
      foreach ($field_data as $field_name => $expected) {
        $actual = $assert_session->waitForElement('css', "[name='settings[block_form][field_test][$delta][$field_name]']")
          ->getValue();

        static::assertSame($expected, $actual);
      }
    }

    // Add more items (via the update block form).
    $more_custom_field_data = [
      ['string_1' => 'Delta 3, string 1', 'string_2' => 'Delta 3, string 2'],
      ['string_1' => 'Delta 4, string 1', 'string_2' => 'Delta 4, string 2'],
      ['string_1' => 'Delta 5, string 1', 'string_2' => 'Delta 5, string 2'],
    ];

    foreach ($more_custom_field_data as $delta => $field_data) {
      $delta += count($custom_field_data);

      if ($delta < count($custom_field_data) + count($more_custom_field_data)) {
        // Click the 'Add another', button.
        $page->pressButton('Add another item');
        $assert_session->assertWaitOnAjaxRequest();
      }

      foreach ($field_data as $field_name => $string_value) {
        $assert_session->waitForElement('css', "[name='settings[block_form][field_test][$delta][$field_name]']")
          ->setValue($string_value);
      }
    }

    $page->pressButton('Update');

    $custom_field_data = array_merge($custom_field_data, $more_custom_field_data);

    $assert_session->waitForElement('css', '.customfield');

    // Make sure the page displays all item data.
    foreach ($custom_field_data as $field_data) {
      foreach ($field_data as $string_value) {
        $assert_session->pageTextContainsOnce($string_value);
      }
    }

    // Edit the block and ensure the edit form values are correct.
    $this->clickContextualLink('.layout-builder-block', 'Configure');
    foreach ($custom_field_data as $delta => $field_data) {
      foreach ($field_data as $field_name => $expected) {
        $actual = $assert_session->waitForElement('css', "[name='settings[block_form][field_test][$delta][$field_name]']")
          ->getValue();

        static::assertSame($expected, $actual);
      }
    }

    // Remove an item.
    $delta_to_remove = count($custom_field_data) - 1;
    $assert_session->waitForElement('css', "[name='settings[block_form][field_test][$delta_to_remove][string_1]']")
      ->setValue('');
    $assert_session->waitForElement('css', "[name='settings[block_form][field_test][$delta_to_remove][string_2]']")
      ->setValue('');

    $page->pressButton('Update');

    $assert_session->waitForElement('css', '.customfield');

    // Ensure that all except the last data items still exist on the page.
    for ($delta = 0; $delta < $delta_to_remove; ++$delta) {
      $field_data = $custom_field_data[$delta];
      foreach ($field_data as $string_value) {
        $assert_session->pageTextContainsOnce($string_value);
      }
    }

  }

}
