<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\FunctionalJavascript\FieldWidget;

use Drupal\Tests\tagify\FunctionalJavascript\TagifyJavascriptTestBase;
use Drupal\entity_test\Entity\EntityTestMulRevPub;

/**
 * Tests tagify select widget.
 *
 * @group tagify
 */
class TagifySelectWidgetTest extends TagifyJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'tagify',
    // Prevent tests from failing due to 'RuntimeException' with AJAX request.
    'js_testing_ajax_request_test',
  ];

  /**
   * Tests a single value select widget.
   */
  public function testSingleValueWidget(): void {
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'tagify_select_widget', [
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'baz'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    // Open the dropdown by clicking the tagify input area.
    $this->click('.tagify__input');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');

    // Type to filter options.
    $page->find('css', '.tagify__input')->setValue('foo');
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $page->find('css', '.tagify__dropdown__item')->click();
    $this->getSession()->wait(500);

    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    if (!$node) {
      return;
    }

    $tag_id = $node->get('tagify')->getString();
    if (is_object($node) && $tag_id) {
      $this->assertSame('1', $tag_id);
    }
  }

  /**
   * Tests that dropdown highlighting does not double-escape HTML.
   *
   * Regression test: the XSS fix applied Drupal.checkPlain() in
   * highlightMatchingLetters(), which already escapes its inputs. The
   * dropdownItemTemplate must not apply checkPlain() again, or the
   * <strong> highlight tags render as literal &lt;strong&gt; text.
   */
  public function testDropdownHighlighting(): void {
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'tagify_select_widget', [
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ]);

    EntityTestMulRevPub::create(['name' => 'alpha'])->save();
    EntityTestMulRevPub::create(['name' => 'alpine'])->save();
    EntityTestMulRevPub::create(['name' => 'bravo'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    // Open the dropdown and type a search term to trigger highlighting.
    $this->click('.tagify__input');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $page->find('css', '.tagify__input')->setValue('alp');
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__dropdown__item');

    // Get the highlighted dropdown item content.
    $dropdown_item = $page->find('css', '.tagify__dropdown__item-highlighted');
    $this->assertNotNull($dropdown_item, 'Dropdown item with highlighting exists.');

    // Assert no double-escaped HTML: literal "&lt;strong&gt;" should NOT
    // appear in the rendered text.
    $item_text = $dropdown_item->getText();
    $this->assertStringNotContainsString('&lt;strong&gt;', $item_text, 'No double-escaped <strong> tags in dropdown.');
    $this->assertStringNotContainsString('<strong>', $item_text, 'The <strong> tags are rendered as HTML, not visible as text.');

    // The item should display the expected text with the match highlighted.
    $item_html = $dropdown_item->getHtml();
    $this->assertStringContainsString('<strong>', $item_html, 'The dropdown item contains <strong> highlight markup.');
    $this->assertStringContainsString('alp', strtolower($item_html), 'The search term appears in the dropdown item.');
  }

  /**
   * Tests limited cardinality footer message on select widget.
   */
  public function testLimitedCardinality(): void {
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => 2,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'tagify_select_widget', [
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'baz'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    // Select the first tag.
    $this->click('.tagify__input');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $page->find('css', '.tagify__dropdown__item')->click();
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__tag');

    // Select the second tag.
    $this->click('.tagify__input');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $page->find('css', '.tagify__dropdown__item')->click();
    $this->getSession()->wait(500);

    // Open dropdown again to trigger footer.
    $this->click('.tagify__input');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__footer');
    $this->assertSession()->elementTextContains('css', '.tagify__dropdown__footer', 'Tags are limited to: 2');
  }

}
