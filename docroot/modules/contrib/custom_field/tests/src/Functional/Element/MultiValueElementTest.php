<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Functional\Element;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the multi-value form element behavior.
 *
 * @group custom_field
 */
class MultiValueElementTest extends BrowserTestBase {

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
   * Tests the multi-value form element.
   */
  public function testElement(): void {
    $this->drupalGet('/multivalue-form-element/element-test-form');

    $assert_session = $this->assertSession();
    // For unlimited cardinality elements, when no default value is provided,
    // only the first delta is rendered.
    $assert_session->fieldExists('foo[0][text]');
    $assert_session->elementNotExists('css', 'input[name^="foo[1]"]');

    // For limited cardinalities, all the deltas are rendered.
    $assert_session->fieldExists('bar[0][number]');
    $assert_session->fieldExists('bar[1][number]');
    $assert_session->fieldExists('bar[2][number]');
    $assert_session->elementNotExists('css', 'input[name^="bar[3]"]');

    // Add some default values.
    $this->setFormDefaultValues([
      'foo' => ['a', 'b'],
      'bar' => [1, 2, 3, 4],
    ]);
    $this->drupalGet('/multivalue-form-element/element-test-form');

    // For unlimited cardinality elements, elements get generated for each
    // delta of the default value.
    $this->assertEquals('a', $assert_session->fieldExists('foo[0][text]')->getValue());
    $this->assertEquals('b', $assert_session->fieldExists('foo[1][text]')->getValue());
    // Next deltas are not rendered.
    $assert_session->elementNotExists('css', 'input[name^="foo[2]"]');

    // For limited cardinalities, extra values are discarded and only the
    // maximum cardinality is rendered.
    $this->assertEquals('1', $assert_session->fieldExists('bar[0][number]')->getValue());
    $this->assertEquals('2', $assert_session->fieldExists('bar[1][number]')->getValue());
    $this->assertEquals('3', $assert_session->fieldExists('bar[2][number]')->getValue());
    $assert_session->elementNotExists('css', 'input[name^="bar[3]"]');

    // Test that passing non-contiguous deltas is handled.
    $this->setFormDefaultValues([
      'foo' => [
        1 => 'c',
        5 => 'd',
      ],
    ]);
    $this->drupalGet('/multivalue-form-element/element-test-form');

    $this->assertEquals('c', $assert_session->fieldExists('foo[0][text]')->getValue());
    $this->assertEquals('d', $assert_session->fieldExists('foo[1][text]')->getValue());
    $assert_session->elementNotExists('css', 'input[name^="foo[5]"]');

    // Test behavior for elements with multiple children.
    $this->setFormDefaultValues([
      'complex' => [
        [
          'text' => 'e',
          'number' => 5,
        ],
        [
          'text' => 'f',
          'number' => 6,
        ],
      ],
    ]);
    $this->drupalGet('/multivalue-form-element/element-test-form');

    $this->assertEquals('e', $assert_session->fieldExists('complex[0][text]')->getValue());
    $this->assertEquals('5', $assert_session->fieldExists('complex[0][number]')->getValue());
    $this->assertEquals('f', $assert_session->fieldExists('complex[1][text]')->getValue());
    $this->assertEquals('6', $assert_session->fieldExists('complex[1][number]')->getValue());
    $assert_session->elementNotExists('css', 'input[name^="complex[2]"]');

    // Test that the add more button label can be overridden.
    $this->assertEquals('Add another item', $assert_session->buttonExists('foo_add_more')->getValue());
    $this->assertEquals('Add more complexity', $assert_session->buttonExists('complex_add_more')->getValue());

    // Test that the button name and AJAX wrapper ID are unique and take into
    // account the form structure.
    $wrapper = $assert_session->elementExists('css', 'div#nested-inner-foo-add-more-wrapper');
    $assert_session->buttonExists('nested_inner_foo_add_more', $wrapper);

    // Test that the add more button works correctly without JavaScript.
    $assert_session->buttonExists('foo_add_more')->press();
    $assert_session->fieldExists('foo[0][text]');
    $assert_session->fieldExists('foo[1][text]');
    $assert_session->elementNotExists('css', 'input[name^="foo[2]"]');

    // Test that the max weight reflects the numbers of items available.
    $expected_weight_range = range(-2, 2);
    $this->assertSelectOptions($expected_weight_range, 'foo[0][_weight]');
    $this->assertSelectOptions($expected_weight_range, 'foo[1][_weight]');
    $assert_session->buttonExists('foo_add_more')->press();
    $expected_weight_range = range(-3, 3);
    $this->assertSelectOptions($expected_weight_range, 'foo[0][_weight]');
    $this->assertSelectOptions($expected_weight_range, 'foo[1][_weight]');
    $this->assertSelectOptions($expected_weight_range, 'foo[2][_weight]');

    // Reset all the default values.
    $this->setFormDefaultValues([]);
    $this->drupalGet('/multivalue-form-element/element-test-form');

    // Set some values around to test submissions.
    $assert_session->fieldExists('complex[0][text]')->setValue('g');
    $assert_session->fieldExists('complex[0][number]')->setValue(7);
    $add_more = $assert_session->buttonExists('complex_add_more');
    // Add one more delta, but leave it empty.
    $add_more->press();
    // Add another item but fill only one of the two values.
    $add_more->press();
    $assert_session->fieldExists('complex[2][text]')->setValue('FALSE');
    // Another item with the number set to a "falsy" value.
    $add_more->press();
    $assert_session->fieldExists('complex[3][number]')->setValue('0');

    // Submit the form.
    $assert_session->buttonExists('Submit')->press();
    $submitted_values = $this->getSubmittedFormValues();
    // The submitted values do not contain the "add more" button, have
    // consecutive deltas and the empty entries have been dropped.
    $this->assertEquals([
      0 => [
        'text' => 'g',
        'number' => '7',
      ],
      1 => [
        'text' => 'FALSE',
        'number' => '',
      ],
      2 => [
        'text' => '',
        'number' => '0',
      ],
    ], $submitted_values['complex']);

    // The other elements were empty, so they all result to empty arrays.
    $this->assertEquals([], $submitted_values['foo']);
    $this->assertEquals([], $submitted_values['bar']);
    $this->assertEquals(['inner' => ['foo' => []]], $submitted_values['nested']);

    // Test that the value cleaning works correctly for elements with arrays as
    // value, like checkboxes.
    $assert_session->buttonExists('nested_inner_foo_add_more')->press();
    // Leave the first delta empty and make a selection in the second.
    $assert_session->fieldExists('nested[inner][foo][1][bar][a]')->check();
    $assert_session->buttonExists('Submit')->press();
    $submitted_values = $this->getSubmittedFormValues();
    $this->assertEquals([
      0 => [
        'bar' => [
          'a' => 'a',
          'b' => 0,
        ],
      ],
    ], $submitted_values['nested']['inner']['foo']);

    // Verify that deltas are correctly reordered, based on their weight.
    $assert_session->fieldExists('bar[0][number]')->setValue(1);
    $assert_session->fieldExists('bar[0][_weight]')->setValue(0);
    $assert_session->fieldExists('bar[1][number]')->setValue(2);
    $assert_session->fieldExists('bar[1][_weight]')->setValue(-2);
    $assert_session->fieldExists('bar[2][number]')->setValue(3);
    $assert_session->fieldExists('bar[2][_weight]')->setValue(-1);
    $assert_session->buttonExists('Submit')->press();
    $submitted_values = $this->getSubmittedFormValues();
    $this->assertEquals([
      0 => [
        'number' => 2,
      ],
      1 => [
        'number' => 3,
      ],
      2 => [
        'number' => 1,
      ],
    ], $submitted_values['bar']);

    // Reset all the default values.
    $this->setFormDefaultValues([]);
    // Test behavior for elements with deep nesting.
    $this->setFormDefaultValues([
      'nested_deep' => [
        0 => [
          'enabled' => TRUE,
          'content' => [
            'title' => 'Card 1 title',
            'body' => 'Card 1 body',
            'tags' => [
              0 => ['tag' => 'Tag 1'],
              1 => ['tag' => 'Tag 2'],
            ],
            'content_2' => [
              'title' => 'Card 1 - Content 2 title',
              'body' => 'Card 1 - Content 2 body',
              'tags' => [
                0 => ['tag' => 'Card 1 - Content 2 - Tag 1'],
                1 => ['tag' => 'Card 1 - Content 2 - Tag 2'],
              ],
              'content_3' => [
                'title' => 'Card 1 - Content 3 title',
                'body' => 'Card 1 - Content 3 body',
                'tags' => [
                  0 => ['tag' => 'Card 1 - Content 3 - Tag 1'],
                  1 => ['tag' => 'Card 1 - Content 3 - Tag 2'],
                ],
              ],
            ],
          ],
        ],
        1 => [
          'enabled' => TRUE,
          'content' => [
            'title' => 'Card 2 title',
            'body' => 'Card 2 body',
            'tags' => [
              0 => ['tag' => 'Tag 1'],
              1 => ['tag' => 'Tag 2'],
            ],
            'content_2' => [
              'title' => 'Card 2 - Content 2 title',
              'body' => 'Card 2 - Content 2 body',
              'tags' => [
                0 => ['tag' => 'Card 2 - Content 2 - Tag 1'],
                1 => ['tag' => 'Card 2 - Content 2 - Tag 2'],
              ],
              'content_3' => [
                'title' => 'Card 2 - Content 3 title',
                'body' => 'Card 2 - Content 3 body',
                'tags' => [
                  0 => ['tag' => 'Card 2 - Content 3 - Tag 1'],
                  1 => ['tag' => 'Card 2 - Content 3 - Tag 2'],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $this->drupalGet('/multivalue-form-element/element-test-form');

    // Test first item level 1 elements are rendered.
    $enabled_checkbox = $assert_session->fieldExists('nested_deep[0][enabled]');
    $this->assertTrue($enabled_checkbox->isChecked());
    $this->assertEquals('Card 1 title', $assert_session->fieldExists('nested_deep[0][content][title]')->getValue());
    $this->assertEquals('Card 1 body', $assert_session->fieldExists('nested_deep[0][content][body]')->getValue());
    $this->assertEquals('Tag 1', $assert_session->fieldExists('nested_deep[0][content][tags][0][tag]')->getValue());
    $this->assertEquals('Tag 2', $assert_session->fieldExists('nested_deep[0][content][tags][1][tag]')->getValue());
    // Add a new tag.
    $add_tags = $assert_session->buttonExists('nested_deep_0_content_tags_add_more');
    $add_tags->press();
    $assert_session->fieldExists('nested_deep[0][content][tags][2][tag]')->setValue('New Tag 3');
    // Test first item level 2 elements are rendered.
    $this->assertEquals('Card 1 - Content 2 title', $assert_session->fieldExists('nested_deep[0][content][content_2][title]')->getValue());
    $this->assertEquals('Card 1 - Content 2 body', $assert_session->fieldExists('nested_deep[0][content][content_2][body]')->getValue());
    $this->assertEquals('Card 1 - Content 2 - Tag 1', $assert_session->fieldExists('nested_deep[0][content][content_2][tags][0][tag]')->getValue());
    $this->assertEquals('Card 1 - Content 2 - Tag 2', $assert_session->fieldExists('nested_deep[0][content][content_2][tags][1][tag]')->getValue());
    // Add a new tag on level 2.
    $add_tags_level2 = $assert_session->buttonExists('nested_deep_0_content_content_2_tags_add_more');
    $add_tags_level2->press();
    $assert_session->fieldExists('nested_deep[0][content][content_2][tags][2][tag]')->setValue('New Tag 3 for Card 1 - Content 2');
    // Test first item level 3 elements are rendered.
    $this->assertEquals('Card 1 - Content 3 title', $assert_session->fieldExists('nested_deep[0][content][content_2][content_3][title]')->getValue());
    $this->assertEquals('Card 1 - Content 3 body', $assert_session->fieldExists('nested_deep[0][content][content_2][content_3][body]')->getValue());
    $this->assertEquals('Card 1 - Content 3 - Tag 1', $assert_session->fieldExists('nested_deep[0][content][content_2][content_3][tags][0][tag]')->getValue());
    $this->assertEquals('Card 1 - Content 3 - Tag 2', $assert_session->fieldExists('nested_deep[0][content][content_2][content_3][tags][1][tag]')->getValue());
    // Add a new tag on level 3.
    $add_tags_level3 = $assert_session->buttonExists('nested_deep_0_content_content_2_content_3_tags_add_more');
    $add_tags_level3->press();
    $assert_session->fieldExists('nested_deep[0][content][content_2][content_3][tags][2][tag]')->setValue('New Tag 3 for Card 1 - Content 2 - Content 3');

    // Test second item level 1 elements are rendered.
    $enabled_checkbox_2 = $assert_session->fieldExists('nested_deep[1][enabled]');
    $this->assertTrue($enabled_checkbox_2->isChecked());
    $this->assertEquals('Card 2 title', $assert_session->fieldExists('nested_deep[1][content][title]')->getValue());
    $this->assertEquals('Card 2 body', $assert_session->fieldExists('nested_deep[1][content][body]')->getValue());
    $this->assertEquals('Tag 1', $assert_session->fieldExists('nested_deep[1][content][tags][0][tag]')->getValue());
    $this->assertEquals('Tag 2', $assert_session->fieldExists('nested_deep[1][content][tags][1][tag]')->getValue());
    // Add a new tag.
    $add_tags2 = $assert_session->buttonExists('nested_deep_1_content_tags_add_more');
    $add_tags2->press();
    $assert_session->fieldExists('nested_deep[1][content][tags][2][tag]')->setValue('New Tag 3');
    // Test second item level 2 elements are rendered.
    $this->assertEquals('Card 2 - Content 2 title', $assert_session->fieldExists('nested_deep[1][content][content_2][title]')->getValue());
    $this->assertEquals('Card 2 - Content 2 body', $assert_session->fieldExists('nested_deep[1][content][content_2][body]')->getValue());
    $this->assertEquals('Card 2 - Content 2 - Tag 1', $assert_session->fieldExists('nested_deep[1][content][content_2][tags][0][tag]')->getValue());
    $this->assertEquals('Card 2 - Content 2 - Tag 2', $assert_session->fieldExists('nested_deep[1][content][content_2][tags][1][tag]')->getValue());
    // Add a new tag on level 2.
    $add_tags2_level2 = $assert_session->buttonExists('nested_deep_1_content_content_2_tags_add_more');
    $add_tags2_level2->press();
    $assert_session->fieldExists('nested_deep[1][content][content_2][tags][2][tag]')->setValue('New Tag 3 for Card 2 - Content 2');
    // Test second item level 3 elements are rendered.
    $this->assertEquals('Card 2 - Content 3 title', $assert_session->fieldExists('nested_deep[1][content][content_2][content_3][title]')->getValue());
    $this->assertEquals('Card 2 - Content 3 body', $assert_session->fieldExists('nested_deep[1][content][content_2][content_3][body]')->getValue());
    $this->assertEquals('Card 2 - Content 3 - Tag 1', $assert_session->fieldExists('nested_deep[1][content][content_2][content_3][tags][0][tag]')->getValue());
    $this->assertEquals('Card 2 - Content 3 - Tag 2', $assert_session->fieldExists('nested_deep[1][content][content_2][content_3][tags][1][tag]')->getValue());
    // Add a new tag on level 3.
    $add_tags2_level3 = $assert_session->buttonExists('nested_deep_1_content_content_2_content_3_tags_add_more');
    $add_tags2_level3->press();
    $assert_session->fieldExists('nested_deep[1][content][content_2][content_3][tags][2][tag]')->setValue('New Tag 3 for Card 2 - Content 2 - Content 3');
    // Test adding a new item.
    $add_more = $assert_session->buttonExists('nested_deep_add_more');
    $add_more->press();

    // Test setting values on the new item.
    $enabled_checkbox_3 = $assert_session->fieldExists('nested_deep[2][enabled]');
    $enabled_checkbox_3->check();
    $assert_session->fieldExists('nested_deep[2][content][title]')->setValue('Card 3 title');
    $assert_session->fieldExists('nested_deep[2][content][body]')->setValue('Card 3 body');
    $assert_session->fieldExists('nested_deep[2][content][tags][0][tag]')->setValue('Tag 1');
    // Add a new tag.
    $add_tags3 = $assert_session->buttonExists('nested_deep_2_content_tags_add_more');
    $add_tags3->press();
    $assert_session->fieldExists('nested_deep[2][content][tags][1][tag]')->setValue('Tag 2');

    // Set level 2 values.
    $assert_session->fieldExists('nested_deep[2][content][content_2][title]')->setValue('Card 3 - Content 2 title');
    $assert_session->fieldExists('nested_deep[2][content][content_2][body]')->setValue('Card 3 - Content 2 body');
    $assert_session->fieldExists('nested_deep[2][content][content_2][tags][0][tag]')->setValue('Card 3 - Content 2 - Tag 1');
    // Add a new tag on level 2.
    $add_tags3_level2 = $assert_session->buttonExists('nested_deep_2_content_content_2_tags_add_more');
    $add_tags3_level2->press();
    $assert_session->fieldExists('nested_deep[2][content][content_2][tags][1][tag]')->setValue('Card 3 - Content 2 - Tag 2');

    // Set level 3 values.
    $assert_session->fieldExists('nested_deep[2][content][content_2][content_3][title]')->setValue('Card 3 - Content 3 title');
    $assert_session->fieldExists('nested_deep[2][content][content_2][content_3][body]')->setValue('Card 3 - Content 3 body');
    $assert_session->fieldExists('nested_deep[2][content][content_2][content_3][tags][0][tag]')->setValue('Card 3 - Content 3 - Tag 1');
    // Add a new tag on level 3.
    $add_tags3_level3 = $assert_session->buttonExists('nested_deep_2_content_content_2_content_3_tags_add_more');
    $add_tags3_level3->press();
    $assert_session->fieldExists('nested_deep[2][content][content_2][content_3][tags][1][tag]')->setValue('Card 3 - Content 3 - Tag 2');

    // Submit the form.
    $assert_session->buttonExists('Submit')->press();
    $submitted_values = $this->getSubmittedFormValues();
    $this->assertEquals([
      0 => [
        'enabled' => "1",
        'content' => [
          'title' => 'Card 1 title',
          'body' => 'Card 1 body',
          'tags' => [
            0 => ['tag' => 'Tag 1'],
            1 => ['tag' => 'Tag 2'],
            2 => ['tag' => 'New Tag 3'],
          ],
          'content_2' => [
            'title' => 'Card 1 - Content 2 title',
            'body' => 'Card 1 - Content 2 body',
            'tags' => [
              0 => ['tag' => 'Card 1 - Content 2 - Tag 1'],
              1 => ['tag' => 'Card 1 - Content 2 - Tag 2'],
              2 => ['tag' => 'New Tag 3 for Card 1 - Content 2'],
            ],
            'content_3' => [
              'title' => 'Card 1 - Content 3 title',
              'body' => 'Card 1 - Content 3 body',
              'tags' => [
                0 => ['tag' => 'Card 1 - Content 3 - Tag 1'],
                1 => ['tag' => 'Card 1 - Content 3 - Tag 2'],
                2 => ['tag' => 'New Tag 3 for Card 1 - Content 2 - Content 3'],
              ],
            ],
          ],
        ],
      ],
      1 => [
        'enabled' => "1",
        'content' => [
          'title' => 'Card 2 title',
          'body' => 'Card 2 body',
          'tags' => [
            0 => ['tag' => 'Tag 1'],
            1 => ['tag' => 'Tag 2'],
            2 => ['tag' => 'New Tag 3'],
          ],
          'content_2' => [
            'title' => 'Card 2 - Content 2 title',
            'body' => 'Card 2 - Content 2 body',
            'tags' => [
              0 => ['tag' => 'Card 2 - Content 2 - Tag 1'],
              1 => ['tag' => 'Card 2 - Content 2 - Tag 2'],
              2 => ['tag' => 'New Tag 3 for Card 2 - Content 2'],
            ],
            'content_3' => [
              'title' => 'Card 2 - Content 3 title',
              'body' => 'Card 2 - Content 3 body',
              'tags' => [
                0 => ['tag' => 'Card 2 - Content 3 - Tag 1'],
                1 => ['tag' => 'Card 2 - Content 3 - Tag 2'],
                2 => ['tag' => 'New Tag 3 for Card 2 - Content 2 - Content 3'],
              ],
            ],
          ],
        ],
      ],
      2 => [
        'enabled' => "1",
        'content' => [
          'title' => 'Card 3 title',
          'body' => 'Card 3 body',
          'tags' => [
            0 => ['tag' => 'Tag 1'],
            1 => ['tag' => 'Tag 2'],
          ],
          'content_2' => [
            'title' => 'Card 3 - Content 2 title',
            'body' => 'Card 3 - Content 2 body',
            'tags' => [
              0 => ['tag' => 'Card 3 - Content 2 - Tag 1'],
              1 => ['tag' => 'Card 3 - Content 2 - Tag 2'],
            ],
            'content_3' => [
              'title' => 'Card 3 - Content 3 title',
              'body' => 'Card 3 - Content 3 body',
              'tags' => [
                0 => ['tag' => 'Card 3 - Content 3 - Tag 1'],
                1 => ['tag' => 'Card 3 - Content 3 - Tag 2'],
              ],
            ],
          ],
        ],
      ],
    ], $submitted_values['nested_deep']);
  }

  /**
   * Sets the default values for some form elements in the test form.
   *
   * @param array $default_values
   *   The default values to use in the form.
   */
  protected function setFormDefaultValues(array $default_values): void {
    \Drupal::state()->set('multivalue_form_element_test_default_values', $default_values);
  }

  /**
   * Returns the submitted form values from the test form.
   *
   * @return array
   *   The submitted test form values.
   */
  protected function getSubmittedFormValues(): array {
    // Make sure to reset the cache to get fresh values from state.
    \Drupal::service('state')->resetCache();
    return \Drupal::state()->get('multivalue_form_element_test_submitted_values', []);
  }

  /**
   * Assert that a select element contains the expected options.
   *
   * @param array $expected_options
   *   The expected option values.
   * @param string $selector
   *   The select element selector.
   */
  protected function assertSelectOptions(array $expected_options, string $selector): void {
    $select_element = $this->assertSession()->selectExists($selector);
    $options = [];
    /** @var \Behat\Mink\Element\NodeElement $option */
    foreach ($select_element->findAll('xpath', '//option') as $option) {
      $label = $option->getText();
      $value = $option->getAttribute('value') ?: $label;
      $options[$value] = $label;
    }
    $this->assertEquals($expected_options, array_keys($options));
  }

}
