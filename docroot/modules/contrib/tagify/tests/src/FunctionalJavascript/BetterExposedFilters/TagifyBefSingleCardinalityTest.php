<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\FunctionalJavascript\BetterExposedFilters;

use Drupal\Tests\tagify\FunctionalJavascript\TagifyJavascriptTestBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a single-value Tagify exposed filter rejects a second tag.
 *
 * The PHP side of this behaviour - Tagify::alterTagifyElement() setting
 * '#cardinality' => 1 when 'expose.multiple' is off, and elementValidate()
 * truncating to one item - is covered by the kernel tests in
 * \Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters\TagifyBefWidgetTest.
 * This browser case exists only to prove the hops that cannot be asserted in
 * PHP: 'data-cardinality="1"' on the rendered input becoming Tagify's
 * 'maxTags: 1', and '#identifier' letting js/tagify.js see the limit is
 * reached, so a second term is refused.
 *
 * @group tagify
 */
#[RunTestsInSeparateProcesses]
class TagifyBefSingleCardinalityTest extends TagifyJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'tagify',
    'options',
    'taxonomy',
    'field',
    'views',
    'better_exposed_filters',
    // Prevent tests from failing due to 'RuntimeException' with AJAX request.
    'js_testing_ajax_request_test',
  ];

  /**
   * The machine name of the test vocabulary.
   */
  const VOCABULARY_ID = 'tagify_bef_tags';

  /**
   * The path of the test view's page display.
   */
  const VIEW_PATH = '/tagify-bef-cardinality';

  /**
   * The test terms, keyed by name.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms = [];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    Vocabulary::create([
      'vid' => self::VOCABULARY_ID,
      'name' => 'Tagify BEF tags',
    ])->save();

    foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
      $term = Term::create(['vid' => self::VOCABULARY_ID, 'name' => $name]);
      $term->save();
      $this->terms[$name] = $term;
    }

    // The field name drives the Views table/column, and therefore the exposed
    // filter identifier ('field_tags_target_id') used throughout this test.
    $this->createField('field_tags', 'node', 'test', 'entity_reference', [
      'target_type' => 'taxonomy_term',
      'cardinality' => -1,
    ], [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => [self::VOCABULARY_ID => self::VOCABULARY_ID],
      ],
    ], 'tagify_entity_reference_autocomplete_widget');

    // Give the view rows so the exposed form renders in a realistic page.
    foreach ($this->terms as $name => $term) {
      Node::create([
        'type' => 'test',
        'title' => 'Node ' . $name,
        'status' => 1,
        'field_tags' => [['target_id' => $term->id()]],
      ])->save();
    }

    $this->createTestView();
  }

  /**
   * A single-value Tagify exposed filter accepts one term and refuses a second.
   */
  public function testSecondTagIsRejected(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet(self::VIEW_PATH);

    // The exposed filter is single-value ('expose.multiple' is FALSE), so the
    // BEF widget must hand Tagify a cardinality of 1. Assert the contract on
    // the rendered input before any interaction: this is the value
    // js/tagify.js reads to compute 'maxTags'.
    $input = $assert_session->elementExists('css', 'input.tagify-widget.field_tags_target_id');
    $this->assertSame('1', $input->getAttribute('data-cardinality'));

    // Wait for Tagify to take over the input.
    $assert_session->waitForElementVisible('css', '.tagify__input');

    // Select the first term through the autocomplete.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('Alpha');
    $this->assertNotNull($assert_session->waitForElement('css', '.tagify__dropdown__item'));
    $this->assertNotNull($assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active'));
    $page->find('css', '.tagify__dropdown__item--active')->click();
    $assert_session->waitForElement('css', '.tagify__tag');

    $this->assertCount(1, $this->acceptedTags(), 'One term is selected.');

    // Attempt a second term the same way. With the limit reached the module
    // hides the suggestions and shows the "Tags are limited to" footer
    // instead, so there is nothing left to click.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('Beta');
    $this->assertNotNull($assert_session->waitForElementVisible('css', '[data-selector="tagify-suggestions-footer"]'));
    // Only the "no matching suggestions" placeholder may remain: no term is
    // offered for selection.
    $assert_session->elementNotExists('css', '.tagify__dropdown__item:not(.tagify--dropdown-item-no-match)');

    // Pressing Enter on the typed text is the way past the dropdown, and
    // 'maxTags' (from 'data-cardinality') still refuses the term.
    $page->find('css', '.tagify__input')->keyPress(13);

    // Give the addition a full second to land before counting, so a passing
    // assertion cannot be an artefact of reading the DOM too early.
    $this->getSession()->wait(
      1000,
      'document.querySelectorAll(".tagify__tag:not(.tagify--notAllowed)").length !== 1',
    );

    // Still exactly one accepted tag. Tagify may leave the refused attempt in
    // the DOM flagged 'tagify--notAllowed', so the count that matters is the
    // accepted tags - the ones Tagify keeps in its value.
    $this->assertCount(1, $this->acceptedTags(), 'The second term was rejected.');

    // And the page did not navigate away mid-attempt, which would make the
    // count above meaningless.
    $this->assertStringContainsString(self::VIEW_PATH, $this->getSession()->getCurrentUrl());

    // The value Drupal would submit still carries only the first term.
    $value = $this->getSession()->evaluateScript(
      "document.querySelector('input.tagify-widget.field_tags_target_id').value",
    );
    $decoded = json_decode((string) $value, TRUE);
    $this->assertIsArray($decoded);
    $this->assertCount(1, $decoded);
    $this->assertSame((string) $this->terms['Alpha']->id(), (string) $decoded[0]['entity_id']);

    // No JavaScript console errors during the interaction. WebDriverTestBase
    // also enforces this as a post-condition; asserting it here keeps the
    // failure attributable to this interaction.
    $errors = $this->getSession()->evaluateScript(
      "JSON.parse(sessionStorage.getItem('js_testing_log_test.errors') || JSON.stringify([]))",
    );
    $this->assertSame([], $errors, 'No JavaScript console errors were logged.');
  }

  /**
   * Returns the tags Tagify accepted, excluding refused attempts.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The accepted tag elements.
   */
  protected function acceptedTags(): array {
    return $this->getSession()->getPage()
      ->findAll('css', '.tagify__tag:not(.tagify--notAllowed)');
  }

  /**
   * Creates the test view with a single-value Tagify exposed filter.
   *
   * Mirrors the shape of the reported case: a 'taxonomy_index_tid' filter of
   * type 'textfield', exposed with 'multiple' off, rendered by the
   * 'bef_tagify' widget.
   */
  protected function createTestView(): void {
    View::create([
      'id' => 'tagify_bef_cardinality',
      'label' => 'Tagify BEF cardinality',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'access' => [
              'type' => 'perm',
              'options' => ['perm' => 'access content'],
            ],
            'cache' => ['type' => 'tag', 'options' => []],
            'query' => ['type' => 'views_query', 'options' => []],
            'pager' => ['type' => 'some', 'options' => ['items_per_page' => 10]],
            'style' => ['type' => 'default', 'options' => []],
            'row' => ['type' => 'fields', 'options' => []],
            'exposed_form' => [
              'type' => 'bef',
              'options' => [
                'submit_button' => 'Filter',
                'reset_button' => FALSE,
                'bef' => [
                  'general' => [
                    'autosubmit' => FALSE,
                    'input_required' => FALSE,
                    'allow_secondary' => FALSE,
                  ],
                  'filter' => [
                    // Only 'plugin_id' is stored: the widget's own 'advanced'
                    // keys (match_operator, max_items, placeholder) have no
                    // entry in better_exposed_filters.filter.bef_tagify's
                    // schema, so writing them here trips the test-only strict
                    // config schema checker. The plugin fills them from
                    // defaultConfiguration() at runtime, which is what this
                    // test exercises anyway.
                    'field_tags_target_id' => [
                      'plugin_id' => 'bef_tagify',
                    ],
                  ],
                ],
              ],
            ],
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
                'entity_type' => 'node',
                'entity_field' => 'title',
                'plugin_id' => 'field',
              ],
            ],
            'filters' => [
              'field_tags_target_id' => [
                'id' => 'field_tags_target_id',
                'table' => 'node__field_tags',
                'field' => 'field_tags_target_id',
                'relationship' => 'none',
                'group_type' => 'group',
                'admin_label' => '',
                'plugin_id' => 'taxonomy_index_tid',
                'operator' => 'or',
                'value' => [],
                'group' => 1,
                'exposed' => TRUE,
                'expose' => [
                  'operator_id' => 'field_tags_target_id_op',
                  'label' => 'Tags',
                  'description' => '',
                  'use_operator' => FALSE,
                  'operator' => 'field_tags_target_id_op',
                  'identifier' => 'field_tags_target_id',
                  'required' => FALSE,
                  'remember' => FALSE,
                  'multiple' => FALSE,
                  'reduce' => FALSE,
                ],
                'is_grouped' => FALSE,
                'reduce_duplicates' => TRUE,
                'vid' => self::VOCABULARY_ID,
                'type' => 'textfield',
                'hierarchy' => FALSE,
                'limit' => TRUE,
                'error_message' => TRUE,
              ],
            ],
          ],
        ],
        'page_1' => [
          'id' => 'page_1',
          'display_title' => 'Page',
          'display_plugin' => 'page',
          'position' => 1,
          'display_options' => [
            'path' => ltrim(self::VIEW_PATH, '/'),
          ],
        ],
      ],
    ])->save();

    \Drupal::service('router.builder')->rebuild();
  }

}
