<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\FunctionalJavascript\Element;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Remove" button functionality for multi-value elements.
 *
 * @group custom_field
 * @runTestsInSeparateProcesses
 */
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
class MultiValueAjaxRemoveTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_multivalue_form_element_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the AJAX "Remove" button.
   */
  public function testRemove(): void {
    $this->drupalGet('/multivalue-form-element/ajax-test-form');

    $assert_session = $this->assertSession();

    // Assert that the "foo" element is empty.
    $assert_session->fieldNotExists('foo[0][textfield]');
    // Assert that the "bar" element is empty.
    $assert_session->fieldNotExists('bar[0][textfield]');

    // Add one foo item.
    $assert_session->buttonExists('foo_add_more')->press();
    $field_foo_0 = $assert_session->waitForField('foo[0][textfield]');
    $this->assertNotNull($field_foo_0, 'foo[0][textfield] should appear after first add_more press.');
    $field_foo_0->setValue('1');

    // Add one bar item.
    $assert_session->buttonExists('bar_add_more')->press();
    $field_bar_0 = $assert_session->waitForField('bar[0][textfield]');
    $this->assertNotNull($field_bar_0, 'bar[0][textfield] should appear after bar add_more press.');

    // Add two more foo items and set their values.
    $assert_session->buttonExists('foo_add_more')->press();
    $field_foo_1 = $assert_session->waitForField('foo[1][textfield]');
    $this->assertNotNull($field_foo_1, 'foo[1][textfield] should appear after second add_more press.');
    $field_foo_1->setValue('2');

    $assert_session->buttonExists('foo_add_more')->press();
    $field_foo_2 = $assert_session->waitForField('foo[2][textfield]');
    $this->assertNotNull($field_foo_2, 'foo[2][textfield] should appear after third add_more press.');
    $field_foo_2->setValue('3');

    // Remove the middle delta and verify the remaining values shift correctly.
    $assert_session->buttonExists('foo_1_remove_button')->press();
    $assert_session->waitForElementRemoved('css', 'input[name="foo[2][textfield]"]');
    $this->assertEquals('1', $assert_session->fieldExists('foo[0][textfield]')->getValue());
    $this->assertEquals('3', $assert_session->fieldExists('foo[1][textfield]')->getValue());
    $assert_session->fieldNotExists('foo[2][textfield]');

    // Verify that the "bar" element was not affected.
    $this->assertEmpty($assert_session->fieldExists('bar[0][textfield]')->getValue());
    $assert_session->fieldNotExists('bar[1][textfield]');

    // Remove the first delta and verify the remaining value shifts correctly.
    $assert_session->buttonExists('foo_0_remove_button')->press();
    $assert_session->waitForElementRemoved('css', 'input[name="foo[1][textfield]"]');
    $this->assertEquals('3', $assert_session->fieldExists('foo[0][textfield]')->getValue());
    $assert_session->fieldNotExists('foo[1][textfield]');

    // Add items to "bar" and verify "foo" is not affected.
    $assert_session->buttonExists('bar_add_more')->press();
    $field_bar_1 = $assert_session->waitForField('bar[1][textfield]');
    $this->assertNotNull($field_bar_1, 'bar[1][textfield] should appear after second bar add_more press.');
    $assert_session->fieldExists('bar[0][textfield]')->setValue('a');
    $field_bar_1->setValue('b');

    // Remove the first "bar" delta and verify "foo" is still intact.
    $assert_session->buttonExists('bar_0_remove_button')->press();
    $assert_session->waitForElementRemoved('css', 'input[name="bar[1][textfield]"]');
    $this->assertEquals('b', $assert_session->fieldExists('bar[0][textfield]')->getValue());
    $assert_session->fieldNotExists('bar[1][textfield]');

    // Verify "foo" was not affected by changes to "bar".
    $this->assertEquals('3', $assert_session->fieldExists('foo[0][textfield]')->getValue());
    $assert_session->fieldNotExists('foo[1][textfield]');

    // Test that weights are correctly updated after removal.
    $assert_session->buttonExists('foo_add_more')->press();
    $assert_session->waitForField('foo[1][textfield]');
    $assert_session->buttonExists('foo_add_more')->press();
    $assert_session->waitForField('foo[2][textfield]');

    $field_weight_0 = $assert_session->fieldExists('foo[0][_weight]');
    $field_weight_1 = $assert_session->fieldExists('foo[1][_weight]');
    $field_weight_2 = $assert_session->fieldExists('foo[2][_weight]');

    $this->assertGreaterThan($field_weight_0->getValue(), $field_weight_1->getValue());
    $this->assertGreaterThan($field_weight_1->getValue(), $field_weight_2->getValue());

    // Remove the middle delta and verify weights remain sequential.
    $assert_session->buttonExists('foo_1_remove_button')->press();
    $assert_session->waitForElementRemoved('css', 'input[name="foo[2][textfield]"]');

    $field_weight_0 = $assert_session->fieldExists('foo[0][_weight]');
    $field_weight_1 = $assert_session->fieldExists('foo[1][_weight]');
    $this->assertGreaterThan($field_weight_0->getValue(), $field_weight_1->getValue());
  }

}
