<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

/**
 * Test cases for the 'custom_stacked' widget plugin.
 *
 * Any tests specific to the stacked widget should go in here.
 *
 * @group custom_field
 */
class StackedWidgetTest extends CustomFieldWidgetTestBase {

  /**
   * {@inheritdoc}
   *
   * Sets up stacked widgets for the entity form display.
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository */
    $entity_display_repository = \Drupal::service('entity_display.repository');

    $fields = [
      'field_test',
      'field_test_multiple',
      'field_test_unlimited',
    ];

    $form_display = $entity_display_repository->getFormDisplay('node', 'custom_field_entity_test');

    // Swap all display components over to the stacked widget.
    foreach ($fields as $field) {
      $component = $form_display->getComponent($field);

      $component['type'] = 'custom_stacked';

      $form_display
        ->setComponent($field, $component)
        ->save();
    }
  }

}
