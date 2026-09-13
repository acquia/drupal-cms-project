<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tagify\Kernel\TagifyKernelTestBase;
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

    return $instance;
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

}
