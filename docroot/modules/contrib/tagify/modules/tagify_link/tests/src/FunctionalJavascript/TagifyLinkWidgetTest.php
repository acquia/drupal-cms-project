<?php

namespace Drupal\Tests\tagify_link\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\tagify\Traits\TagifyTestTrait;

/**
 * Tests the tagify_link_widget on a link field.
 *
 * @group tagify_link
 */
class TagifyLinkWidgetTest extends WebDriverTestBase {

  use TagifyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'link',
    'node',
    'tagify',
    'tagify_link',
    'js_testing_ajax_request_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'test']);
    $user = $this->drupalCreateUser([
      'access content',
      'create test content',
      'edit own test content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests widget renders with the correct CSS class and default match limit.
   */
  public function testWidgetRendersCorrectly(): void {
    $this->createField('field_link', 'node', 'test', 'link', [], [], 'tagify_link_widget', [
      'match_operator' => 'CONTAINS',
      'match_limit' => 20,
    ]);

    $this->drupalGet('/node/add/test');
    $assert = $this->assertSession();

    $assert->elementExists('css', 'input.tagify-link-widget');
    $assert->elementAttributeContains('css', 'input.tagify-link-widget', 'data-match-limit', '20');
    // The JS rebuilds the stored "entity:<type>/<id>" URI from this.
    $assert->elementAttributeContains('css', 'input.tagify-link-widget', 'data-target-type', 'node');
  }

  /**
   * Tests that the match_limit setting is reflected on the rendered element.
   */
  public function testWidgetMatchLimitSetting(): void {
    $this->createField('field_link_limited', 'node', 'test', 'link', [], [], 'tagify_link_widget', [
      'match_operator' => 'CONTAINS',
      'match_limit' => 5,
    ]);

    $this->drupalGet('/node/add/test');
    $assert = $this->assertSession();

    $assert->elementAttributeContains('css', 'input.tagify-link-widget', 'data-match-limit', '5');
  }

}
