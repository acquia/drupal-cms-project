<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tagify\Kernel\TagifyKernelTestBase;
use Drupal\tagify\Plugin\better_exposed_filters\filter\Tagify;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for the Tagify (autocomplete) BEF filter widget.
 *
 * @group tagify
 * @coversDefaultClass \Drupal\tagify\Plugin\better_exposed_filters\filter\Tagify
 */
class TagifyBefWidgetTest extends TagifyKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'user',
    'system',
    'taxonomy',
    'tagify',
    'better_exposed_filters',
    'views',
  ];

  /**
   * Creates a TestableTagify instance with the given configuration.
   *
   * @param array $advanced_config
   *   Overrides for the 'advanced' configuration key.
   *
   * @return \Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters\TestableTagify
   *   The plugin instance.
   */
  protected function createPlugin(array $advanced_config = []): TestableTagify {
    $configuration = [
      'advanced' => array_merge([
        'match_operator' => 'CONTAINS',
        'max_items' => 10,
        'placeholder' => '',
        'collapsible' => FALSE,
        'collapsible_disable_automatic_open' => FALSE,
        'is_secondary' => FALSE,
        'placeholder_text' => '',
        'rewrite' => [
          'filter_rewrite_values' => '',
          'filter_rewrite_values_key' => FALSE,
        ],
        'sort_options' => FALSE,
        'hide_label' => FALSE,
      ], $advanced_config),
    ];

    $plugin_id = 'bef_tagify';
    $plugin_definition = [
      'id' => 'bef_tagify',
      'label' => 'Tagify',
      'provider' => 'tagify',
    ];

    // Pass Request and ConfigFactory to satisfy the 5-parameter constructor
    // signature required by the contrib BetterExposedFiltersWidgetBase.
    $request = Request::create('/');
    $configFactory = $this->container->get('config.factory');
    $instance = new TestableTagify(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $request,
      $configFactory,
    );
    $instance->setStringTranslation($this->container->get('string_translation'));
    // alterTagifyElement() reads $this->handler, a typed property on the BEF
    // base, so every plugin needs one attached before use.
    $this->attachHandler($instance, ['multiple' => FALSE]);

    return $instance;
  }

  /**
   * Attaches a real Views filter handler carrying fabricated expose options.
   *
   * @param \Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters\TestableTagify $plugin
   *   The plugin to attach the handler to.
   * @param array $expose
   *   The 'expose' options array for the handler.
   */
  protected function attachHandler(TestableTagify $plugin, array $expose): void {
    $handler = $this->container
      ->get('plugin.manager.views.filter')
      ->createInstance('taxonomy_index_tid');
    $handler->options = ['expose' => $expose];
    $plugin->setViewsHandler($handler);
  }

  /**
   * Builds the Tagify element for the given expose options.
   *
   * @param array $expose
   *   The handler's 'expose' options.
   *
   * @return array
   *   The altered element.
   */
  protected function buildElementWithExpose(array $expose): array {
    $plugin = $this->createPlugin();
    $this->attachHandler($plugin, $expose);

    $form = [
      'test_field' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
      ],
    ];
    $plugin->exposedFormAlter($form, new FormState());

    return $form['test_field'];
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testEntityReferenceFieldConvertsToEntityAutocompleteTagify(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
        '#selection_handler' => 'default',
        '#selection_settings' => ['target_bundles' => ['tags']],
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $element = $form['test_field'];
    $this->assertEquals('entity_autocomplete_tagify', $element['#type']);
    $this->assertEquals('taxonomy_term', $element['#target_type']);
    $this->assertTrue($element['#tags']);
    $this->assertEquals('CONTAINS', $element['#match_operator']);
    $this->assertEquals(10, $element['#max_items']);
    $this->assertEquals('', $element['#placeholder']);
    $this->assertIsArray($element['#element_validate']);
    $this->assertNotEmpty($element['#element_validate']);
    $this->assertEquals('default', $element['#selection_handler']);
    $this->assertArrayHasKey('#attributes', $element);
    $this->assertContains('test_field', $element['#attributes']['class']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testGracefulFallbackWhenNotEntityReference(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $original_element = [
      '#type' => 'textfield',
      '#title' => 'Some text filter',
    ];
    $form = ['test_field' => $original_element];

    $plugin->exposedFormAlter($form, $form_state);

    $this->assertEquals($original_element, $form['test_field']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testConfigValuesFlowThroughToEntityReferenceElement(): void {
    $plugin = $this->createPlugin([
      'match_operator' => 'STARTS_WITH',
      'max_items' => 5,
      'placeholder' => 'Search for terms…',
    ]);
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#tags' => TRUE,
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $element = $form['test_field'];
    $this->assertEquals('STARTS_WITH', $element['#match_operator']);
    $this->assertEquals(5, $element['#max_items']);
    $this->assertEquals('Search for terms…', $element['#placeholder']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testOptionsFieldIsNotConvertedByTagify(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $original_element = [
      '#type' => 'select',
      '#options' => ['opt' => 'Option'],
      '#multiple' => FALSE,
    ];
    $form = ['test_field' => $original_element];

    $plugin->exposedFormAlter($form, $form_state);

    // Tagify (autocomplete) must not convert options-based elements.
    // TagifySelect handles those instead.
    $this->assertEquals('select', $form['test_field']['#type']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testMissingFieldIdInFormCausesEarlyReturn(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = ['other_field' => ['#type' => 'textfield']];

    $plugin->exposedFormAlter($form, $form_state);

    $this->assertArrayNotHasKey('test_field', $form);
    $this->assertArrayHasKey('other_field', $form);
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationContainsRequiredKeys(): void {
    $plugin = $this->createPlugin();
    $config = $plugin->getConfiguration();

    $this->assertArrayHasKey('advanced', $config);
    $this->assertEquals('CONTAINS', $config['advanced']['match_operator']);
    $this->assertEquals(10, $config['advanced']['max_items']);
    $this->assertEquals('', $config['advanced']['placeholder']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testCardinalityIsSingleWhenMultipleIsDisabled(): void {
    $element = $this->buildElementWithExpose(['multiple' => FALSE]);

    $this->assertSame(1, $element['#cardinality']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testCardinalityIsUnlimitedWhenMultipleIsEnabled(): void {
    $element = $this->buildElementWithExpose(['multiple' => TRUE]);

    $this->assertSame(-1, $element['#cardinality']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testCardinalityDefaultsToSingleWhenExposeMultipleIsAbsent(): void {
    $element = $this->buildElementWithExpose([]);

    $this->assertSame(1, $element['#cardinality']);
  }

  /**
   * @covers ::elementValidate
   */
  public function testValidateTruncatesToFirstItemForSingleCardinality(): void {
    $form_state = new FormState();
    $form_state->setValue(['test_field'], json_encode([
      ['entity_id' => 13, 'label' => 'Term 13'],
      ['entity_id' => 1, 'label' => 'Term 1'],
    ]));
    $element = ['#parents' => ['test_field'], '#cardinality' => 1];

    Tagify::elementValidate($element, $form_state);

    $this->assertSame(
      [['target_id' => 13]],
      $form_state->getValue(['test_field'])
    );
  }

  /**
   * @covers ::elementValidate
   */
  public function testValidateKeepsAllItemsForUnlimitedCardinality(): void {
    $form_state = new FormState();
    $form_state->setValue(['test_field'], json_encode([
      ['entity_id' => 13, 'label' => 'Term 13'],
      ['entity_id' => 1, 'label' => 'Term 1'],
    ]));
    $element = ['#parents' => ['test_field'], '#cardinality' => -1];

    Tagify::elementValidate($element, $form_state);

    $this->assertSame(
      [['target_id' => 13], ['target_id' => 1]],
      $form_state->getValue(['test_field'])
    );
  }

  /**
   * @covers ::elementValidate
   */
  public function testValidateTruncatesAfterFilteringMalformedItems(): void {
    $form_state = new FormState();
    $form_state->setValue(['test_field'], json_encode([
      ['entity_id' => 'not-a-number', 'label' => 'Junk'],
      ['entity_id' => 7, 'label' => 'Term 7'],
    ]));
    $element = ['#parents' => ['test_field'], '#cardinality' => 1];

    Tagify::elementValidate($element, $form_state);

    // Truncation must run after formattedItems() filters the malformed entry,
    // otherwise the surviving slice would be empty.
    $this->assertSame(
      [['target_id' => 7]],
      $form_state->getValue(['test_field'])
    );
  }

  /**
   * Properties set by Views and the BEF base survive the element rebuild.
   *
   * @covers ::alterTagifyElement
   */
  public function testExistingElementPropertiesArePreserved(): void {
    $plugin = $this->createPlugin();
    $this->attachHandler($plugin, ['multiple' => FALSE]);
    $description = 'Filter by tag.';

    $form = [
      'test_field' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
        // Set by Views.
        '#title' => 'Tags',
        '#description' => $description,
        // Set by FilterWidgetBase: Hide label, Collapsible, Sort options.
        '#title_display' => 'invisible',
        '#group' => 'test_field_collapsible',
        '#pre_process' => [['stub', 'callback']],
        '#weight' => 3,
        '#required' => TRUE,
      ],
    ];
    $plugin->exposedFormAlter($form, new FormState());

    $element = $form['test_field'];
    $this->assertSame('entity_autocomplete_tagify', $element['#type']);
    $this->assertSame('Tags', $element['#title']);
    $this->assertSame($description, $element['#description']);
    $this->assertSame('invisible', $element['#title_display']);
    // Lose this and the field renders outside its details wrapper.
    $this->assertSame('test_field_collapsible', $element['#group']);
    $this->assertSame([['stub', 'callback']], $element['#pre_process']);
    $this->assertSame(3, $element['#weight']);
    $this->assertTrue($element['#required']);
  }

  /**
   * A property this widget owns is not clobbered by the preserved set.
   *
   * @covers ::alterTagifyElement
   */
  public function testWidgetOwnedPlaceholderWinsOverExistingValue(): void {
    $plugin = $this->createPlugin(['placeholder' => 'Widget placeholder']);
    $this->attachHandler($plugin, ['multiple' => FALSE]);

    $form = [
      'test_field' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
        '#placeholder' => 'BEF placeholder_text',
      ],
    ];
    $plugin->exposedFormAlter($form, new FormState());

    $this->assertSame('Widget placeholder', $form['test_field']['#placeholder']);
  }

  /**
   * The cardinality cap tolerates a stringified '#cardinality'.
   *
   * @covers ::elementValidate
   */
  public function testValidateTruncatesWhenCardinalityIsStringOne(): void {
    $form_state = new FormState();
    $form_state->setValue(['test_field'], json_encode([
      ['entity_id' => 13, 'label' => 'Drupal'],
      ['entity_id' => 1, 'label' => 'Term 1'],
    ]));
    // valueCallback() casts, so this must too, or a form_alter that
    // stringifies the property silently uncaps the filter.
    $element = ['#parents' => ['test_field'], '#cardinality' => '1'];

    Tagify::elementValidate($element, $form_state);

    $this->assertSame(
      [['target_id' => 13]],
      $form_state->getValue(['test_field'])
    );
  }

}
