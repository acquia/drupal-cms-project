<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\tagify\Kernel\TagifyKernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for the TagifySelect (dropdown) BEF filter widget.
 *
 * @group tagify
 * @coversDefaultClass \Drupal\tagify\Plugin\better_exposed_filters\filter\TagifySelect
 */
class TagifySelectBefWidgetTest extends TagifyKernelTestBase {

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
   * Creates a TestableTagifySelect instance with the given configuration.
   *
   * @param array $advanced_config
   *   Overrides for the 'advanced' configuration key.
   *
   * @return \Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters\TestableTagifySelect
   *   The plugin instance.
   */
  protected function createPlugin(array $advanced_config = []): TestableTagifySelect {
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

    $plugin_id = 'bef_tagify_select';
    $plugin_definition = [
      'id' => 'bef_tagify_select',
      'label' => 'Tagify Select',
      'provider' => 'tagify',
    ];

    $request = Request::create('/');
    $configFactory = $this->container->get('config.factory');
    $instance = new TestableTagifySelect(
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
  public function testSingleValueOptionsFieldConvertsToSelectTagify(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'select',
        '#options' => [
          'All' => new TranslatableMarkup('- Any -'),
          'option_1' => new TranslatableMarkup('Option 1'),
          'option_2' => new TranslatableMarkup('Option 2'),
        ],
        '#multiple' => FALSE,
        '#title' => 'Test field',
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $element = $form['test_field'];
    $this->assertEquals('select_tagify', $element['#type']);
    $this->assertEquals('select', $element['#mode']);
    $this->assertEquals('test_field', $element['#identifier']);
    $this->assertEquals(1, $element['#cardinality']);
    $this->assertFalse($element['#multiple']);
    $this->assertEquals('Test field', $element['#title']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testMultiValueOptionsFieldConvertsToSelectTagify(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'select',
        '#options' => [
          'option_1' => new TranslatableMarkup('Option 1'),
          'option_2' => new TranslatableMarkup('Option 2'),
        ],
        '#multiple' => TRUE,
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $element = $form['test_field'];
    $this->assertEquals('select_tagify', $element['#type']);
    $this->assertNull($element['#mode']);
    $this->assertNull($element['#identifier']);
    $this->assertEquals(0, $element['#cardinality']);
    $this->assertTrue($element['#multiple']);
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testAllSentinelIsAddedWhenMissing(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'select',
        '#options' => [
          'option_1' => new TranslatableMarkup('Option 1'),
          'option_2' => new TranslatableMarkup('Option 2'),
        ],
        '#multiple' => FALSE,
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $this->assertArrayHasKey('All', $form['test_field']['#options']);
    $this->assertEquals('All', array_key_first($form['test_field']['#options']));
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testAllSentinelIsNotDuplicatedWhenAlreadyPresent(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'select',
        '#options' => [
          'All' => new TranslatableMarkup('- Any -'),
          'option_1' => new TranslatableMarkup('Option 1'),
        ],
        '#multiple' => FALSE,
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $options = $form['test_field']['#options'];
    $all_count = count(array_filter(array_keys($options), fn($k) => $k === 'All'));
    $this->assertEquals(1, $all_count, "The 'All' sentinel key must not be duplicated.");
  }

  /**
   * @covers ::exposedFormAlter
   */
  public function testGracefulFallbackWhenNoOptions(): void {
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
  public function testConfigValuesFlowThroughToOptionsElement(): void {
    $plugin = $this->createPlugin([
      'match_operator' => 'STARTS_WITH',
      'max_items' => 3,
      'placeholder' => 'Filter options…',
    ]);
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'select',
        '#options' => ['option_1' => new TranslatableMarkup('Option 1')],
        '#multiple' => FALSE,
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    $element = $form['test_field'];
    $this->assertEquals('STARTS_WITH', $element['#match_operator']);
    $this->assertEquals(3, $element['#match_limit']);
    $this->assertEquals('Filter options…', $element['#placeholder']);
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
  public function testEntityReferenceFieldIsNotConvertedByTagifySelect(): void {
    $plugin = $this->createPlugin();
    $form_state = new FormState();

    $form = [
      'test_field' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
      ],
    ];

    $plugin->exposedFormAlter($form, $form_state);

    // TagifySelect must not convert entity_autocomplete elements;
    // that is Tagify's responsibility.
    $this->assertEquals('entity_autocomplete', $form['test_field']['#type']);
  }

}
