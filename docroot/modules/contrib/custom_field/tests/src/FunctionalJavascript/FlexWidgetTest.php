<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

/**
 * Test cases for the 'custom_flex' widget plugin.
 *
 * Any tests specific to the flex widget should go in here.
 *
 * @group custom_field
 */
class FlexWidgetTest extends CustomFieldWidgetTestBase {

  /**
   * {@inheritdoc}
   *
   * Sets up flex widgets for the entity form display.
   */
  protected function setUp(): void {
    parent::setUp();

    $fields = [
      'field_test',
      'field_test_multiple',
      'field_test_unlimited',
    ];

    $form_display = $this->entityDisplayRepository->getFormDisplay('node', 'custom_field_entity_test');

    // Swap all display components over to the stacked widget.
    foreach ($fields as $field) {
      $component = $form_display->getComponent($field);
      $component['type'] = 'custom_flex';
      // Set some random column classes.
      foreach ($component['settings']['columns'] as $subfield => $flex) {
        $component['settings']['columns'][$subfield] = (string) mt_rand(3, 12);
      }
      $form_display
        ->setComponent($field, $component)
        ->save();
    }
  }

  /**
   * Tests the custom field widgets for correct column classes.
   */
  public function testColumnClasses(): void {
    $form_display = $this->entityDisplayRepository->getFormDisplay('node', 'custom_field_entity_test');
    $component = $form_display->getComponent('field_test');
    $columns = $component['settings']['columns'];
    if (isset($columns['uuid_test'])) {
      // The uuid field is never visible in the form so unset it.
      unset($columns['uuid_test']);
    }
    // Load the node add form.
    $this->drupalGet('/node/add/custom_field_entity_test');
    $page = $this->getSession()->getPage();
    $field = $page->find('css', '#edit-field-test-0');
    $this->assertNotNull($field, 'The field exists');
    $row = $field->find('css', '.custom-field-row');
    $this->assertNotNull($row, 'The field row exists');
    foreach ($columns as $subfield => $column) {
      $row_column = $row->find('css', ".custom-field-$subfield");
      $this->assertNotNull($row_column, 'The field exists');
      // Verify the outermost field wrapper has the correct column class.
      $this->assertTrue($row_column->hasClass("custom-field-col-$column"), 'The custom field has the correct flex class.');
    }
  }

}
