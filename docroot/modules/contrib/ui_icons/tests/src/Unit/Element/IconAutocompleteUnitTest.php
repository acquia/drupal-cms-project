<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit\Element;

// cspell:ignore corge quux
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Element\IconAutocomplete;
use Drupal\ui_icons\IconSearch;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test the IconAutocomplete class.
 *
 * @internal
 */
#[CoversClass(IconAutocomplete::class)]
#[Group('ui_icons')]
class IconAutocompleteUnitTest extends UnitTestCase {

  use IconTestTrait;

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  private ContainerBuilder $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $this->container->set('plugin.manager.icon_pack', $this->createMock(IconPackManagerInterface::class));
    $this->container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($this->container);
  }

  /**
   * Test the getInfo method.
   */
  public function testGetInfo(): void {
    $iconAutocomplete = new IconAutocomplete([], 'test', 'test');
    $info = $iconAutocomplete->getInfo();

    $class = 'Drupal\ui_icons\Element\IconAutocomplete';
    $expected = [
      '#input' => TRUE,
      '#element_validate' => [
        [$class, 'validateIcon'],
      ],
      '#process' => [
        [$class, 'processIcon'],
        [$class, 'processIconAjaxForm'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#theme' => 'icon_selector',
      '#theme_wrappers' => ['form_element'],
      '#allowed_icon_pack' => [],
      '#result_format' => 'list',
      '#max_result' => IconSearch::SEARCH_RESULT,
      '#show_settings' => FALSE,
      '#default_settings' => [],
    ];

    foreach ($expected as $key => $value) {
      $this->assertArrayHasKey($key, $info);
      $this->assertSame($value, $info[$key]);
    }
  }

  /**
   * Test the processIcon method.
   */
  public function testProcessIcon(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $complete_form = [];

    // phpcs:disable
    $value = new class implements \Stringable {function __toString() {
        return 'foo';
      }
    };
    // phpcs:enable

    $element = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#value' => $value,
    ];

    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $expected = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#tree' => TRUE,
      '#value' => [],
      'icon_id' => [
        '#type' => 'search',
        '#title' => new TranslatableMarkup('Icon'),
        '#description' => new TranslatableMarkup('Start typing the name of an icon to select it.'),
        '#placeholder' => '',
        '#title_display' => 'invisible',
        '#autocomplete_route_name' => 'ui_icons.autocomplete',
        '#required' => FALSE,
        '#size' => 55,
        '#maxlength' => 128,
        '#value' => '',
        '#limit_validation_errors' => [$element['#parents']],
      ],
    ];

    $this->assertEquals($expected, $element);

    unset($element['#value'], $expected['#value']);

    // Test basic values and #default_value.
    $values = [
      '#size' => 22,
      '#placeholder' => new TranslatableMarkup('Qux'),
      '#required' => TRUE,
      '#default_value' => 'foo:bar',
    ];
    $element += $values;

    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $expected['#required'] = $values['#required'];
    $expected['#default_value'] = $values['#default_value'];
    $expected['icon_id']['#size'] = $values['#size'];
    $expected['icon_id']['#placeholder'] = $values['#placeholder'];
    $expected['icon_id']['#required'] = $values['#required'];
    $expected['icon_id']['#value'] = $values['#default_value'];

    $this->assertEquals($expected, $element);

    // Test value set used before default.
    $element['#value']['icon_id'] = 'baz:qux';
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertSame('baz:qux', $element['icon_id']['#value']);

    // Test empty allowed with basic values and default value.
    $element['#allowed_icon_pack'] = [];
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertArrayNotHasKey('#autocomplete_query_parameters', $element['icon_id']);

    // Test allowed.
    $element['#allowed_icon_pack'] = ['corge', 'quux'];
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertArrayHasKey('allowed_icon_pack', $element['icon_id']['#autocomplete_query_parameters']);
    $this->assertSame('corge+quux', $element['icon_id']['#autocomplete_query_parameters']['allowed_icon_pack']);

    // Test search format and result.
    $element['#max_result'] = 666;
    $element['#result_format'] = 'grid';
    IconAutocomplete::processIcon($element, $form_state, $complete_form);

    $this->assertArrayHasKey('max_result', $element['icon_id']['#autocomplete_query_parameters']);
    $this->assertSame(666, $element['icon_id']['#autocomplete_query_parameters']['max_result']);
    $this->assertArrayHasKey('result_format', $element['icon_id']['#autocomplete_query_parameters']);
    $this->assertSame('grid', $element['icon_id']['#autocomplete_query_parameters']['result_format']);

    // Test values are cleaned on the parent element.
    $this->assertArrayNotHasKey('#size', $element);
    $this->assertArrayNotHasKey('#placeholder', $element);

    // Ensure we still have no settings.
    $this->assertArrayNotHasKey('icon_settings', $element);
  }

  /**
   * Test the processIconAjaxForm method.
   */
  public function testProcessIconAjaxForm(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $complete_form = [];

    $base_element = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#show_settings' => FALSE,
      '#settings_title' => new TranslatableMarkup('Baz'),
    ];

    $element = $base_element;
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertArrayNotHasKey('icon_settings', $element);

    // Test show settings without icon id.
    $element = $base_element;
    $element['#show_settings'] = TRUE;
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertArrayNotHasKey('icon_settings', $element);

    // Test settings enabled with icon_id.
    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->expects($this->once())->method('getIcon')->willReturn($this->createMockIcon());
    $ui_icon_pack_plugin_manager->expects($this->once())
      ->method('getExtractorPluginForms')
      ->with($this->anything())
      ->willReturnCallback(function (&$form): void {
        $form['sub_form'] = TRUE;
      });
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    $element = $base_element;
    $element['#show_settings'] = TRUE;
    $element['#default_value'] = 'bar:baz';
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertSame('baz/qux', $element['icon_id']['#ajax']['options']['query']['element_parents']);

    $this->assertArrayHasKey('icon_settings', $element);
    $this->assertSame('icon[foo_bar]', $element['icon_settings']['#name']);
    $this->assertSame($base_element['#settings_title'], $element['icon_settings']['#title']);

    $this->assertArrayHasKey('sub_form', $element['icon_settings']);
  }

  /**
   * Test the processIconAjaxForm method for #show_settings = FALSE.
   */
  public function testProcessIconAjaxFormNoSettings(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $complete_form = [];

    $icon_id = 'foo:bar';
    $pack_id = 'baz';

    $element = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#show_settings' => FALSE,
      '#settings_title' => new TranslatableMarkup('Baz'),
      '#value' => [
        'icon_id' => $icon_id,
      ],
    ];

    $icon = $this->createTestIcon([
      'pack_id' => $pack_id,
      'icon_id' => $icon_id,
      'source' => 'foo/path',
      'pack_label' => 'Baz',
    ]);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->with('foo:bar')
      ->willReturn($icon);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
  }

  /**
   * Test the processIconAjaxForm #allowed_icon_pack and no extractor form.
   */
  public function testProcessIconAjaxFormAllowedIconPack(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $complete_form = [];

    $icon_id = 'foo:bar';
    $pack_id = 'baz';

    $element = [
      '#parents' => ['foo', 'bar'],
      '#array_parents' => ['baz', 'qux'],
      '#show_settings' => TRUE,
      '#settings_title' => new TranslatableMarkup('Baz'),
      '#value' => [
        'icon_id' => $icon_id,
      ],
    ];

    $icon = $this->createTestIcon([
      'pack_id' => $pack_id,
      'icon_id' => $icon_id,
      'source' => 'foo/path',
      'pack_label' => 'Baz',
    ]);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->with($icon_id)
      ->willReturn($icon);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    // Test with no Extractor form.
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);

    // Test with #allowed_icon_pack value not valid.
    $element['#allowed_icon_pack'] = ['qux'];
    IconAutocomplete::processIcon($element, $form_state, $complete_form);
    IconAutocomplete::processIconAjaxForm($element, $form_state, $complete_form);
    $this->assertArrayHasKey('#ajax', $element['icon_id']);
    $this->assertArrayNotHasKey('icon_settings', $element);
  }

  /**
   * Data provider for ::testValidateIcon().
   *
   * @return array
   *   The data to test.
   */
  public static function providerValidateIcon(): array {
    return [
      'valid icon' => [
        'element' => [
          '#parents' => ['icon'],
          'icon_id' => [
            '#title' => 'Foo',
          ],
        ],
        'pack_id' => 'foo',
        'values' => [
          'icon' => [
            'icon_id' => 'foo:baz',
            'icon_settings' => [
              'foo' => [
                'settings_1' => [],
              ],
            ],
          ],
        ],
        'expected_error' => NULL,
      ],
    ];
  }

  /**
   * Test the validateIcon method.
   *
   * @param array $element
   *   The element data.
   * @param string $pack_id
   *   The icon set id.
   * @param array $values
   *   The values data.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $expected_error
   *   The expected error message or no message.
   */
  #[DataProvider('providerValidateIcon')]
  public function testValidateIcon(array $element, string $pack_id, array $values, ?TranslatableMarkup $expected_error): void {
    $complete_form = [];
    $settings = $values['icon']['icon_settings'];

    $icon = $this->createTestIcon([
      'icon_id' => explode(IconDefinition::ICON_SEPARATOR, $values['icon']['icon_id'])[1],
      'source' => 'foo/bar',
      'pack_id' => $pack_id,
      'pack_label' => $element['icon_id']['#title'],
    ]);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->with($icon->getId())
      ->willReturn($icon);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->method('getValues')
      ->willReturn($values);

    // Main test is to expect the setValueForElement().
    $form_state->expects($this->once())
      ->method('setValueForElement')
      ->with($element, ['icon' => $icon, 'settings' => $settings]);

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);

    // Test #return_id property.
    $element['#return_id'] = TRUE;

    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->method('getValues')
      ->willReturn($values);

    // Main test is to expect the setValueForElement() with only target_id.
    $form_state->expects($this->once())
      ->method('setValueForElement')
      ->with($element, ['target_id' => $values['icon']['icon_id'], 'settings' => $settings]);

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);

    // Test $input_exists is FALSE.
    $element['#parents'] = ['foo'];
    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconNull(): void {
    $complete_form = [];
    $element = [
      '#parents' => ['icon'],
      '#required' => FALSE,
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn(['icon' => []]);

    // The test is to expect the setValueForElement().
    $form_state->expects($this->once())
      ->method('setValueForElement')
      ->with($element, NULL);

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconError(): void {
    $complete_form = [];
    $element = [
      '#parents' => ['icon'],
      'icon_id' => [
        '#title' => 'Foo',
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn(['icon' => ['icon_id' => 'foo:baz']]);

    // The test is to expect the setError().
    $form_state
      ->expects($this->once())
      ->method('setError')
      ->with($element['icon_id'], new TranslatableMarkup('Icon for %title is invalid: %icon.<br>Please search again and select a result in the list.', [
        '%title' => $element['icon_id']['#title'],
        '%icon' => 'foo:baz',
      ]));

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);

  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconErrorNotAllowed(): void {
    $complete_form = [];
    $icon_id = 'bar';
    $pack_id = 'foo';
    $icon_full_id = IconDefinition::createIconId($pack_id, $icon_id);

    $element = [
      '#parents' => ['icon'],
      'icon_id' => [
        '#title' => 'Foo',
      ],
      '#allowed_icon_pack' => ['qux', 'corge'],
    ];

    $icon = $this->createTestIcon([
      'pack_id' => $pack_id,
      'icon_id' => $icon_id,
      'source' => 'foo/path',
      'pack_label' => 'Baz',
    ]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValues')
      ->willReturn(['icon' => ['icon_id' => $icon_full_id]]);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->with($icon_full_id)
      ->willReturn($icon);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    // The test is to expect the setError().
    $form_state
      ->expects($this->once())
      ->method('setError')
      ->with($element['icon_id'], new TranslatableMarkup('Icon for %title is not valid anymore because it is part of icon pack: %pack_id. This field limit icon pack to: %limit.', [
        '%title' => $element['icon_id']['#title'],
        '%pack_id' => $pack_id,
        '%limit' => implode(', ', $element['#allowed_icon_pack']),
      ]));

    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
  }

  /**
   * Test the validateIcon method.
   */
  public function testValidateIconEmpty(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];
    $element = ['#parents' => ['foo']];
    IconAutocomplete::validateIcon($element, $form_state, $complete_form);
    $this->assertEquals(['#parents' => ['foo']], $element);
  }

  /**
   * Test the valueCallback method.
   */
  public function testValueCallback(): void {
    $element = [];

    $icon_id = 'bar';
    $pack_id = 'foo';
    $icon_full_id = IconDefinition::createIconId($pack_id, $icon_id);

    $input = [
      'icon_id' => $icon_full_id,
      'icon_settings' => ['foo' => 'bar'],
    ];

    $icon = $this->createTestIcon([
      'pack_id' => $pack_id,
      'icon_id' => $icon_id,
      'source' => 'foo/path',
      'pack_label' => 'Baz',
    ]);

    $form_state = $this->createMock(FormStateInterface::class);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->with($icon_full_id)
      ->willReturn($icon);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);

    $expected = [
      'icon_id' => $icon_full_id,
      'icon_settings' => $input['icon_settings'],
      'object' => $icon,
    ];
    $this->assertSame($expected, $actual);

    // Test default_value with no icon_id.
    $input = FALSE;
    $element['#default_value'] = $icon_full_id;

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);

    $expected = [
      'object' => $icon,
    ];
    $this->assertSame($expected, $actual);

    // Test empty icon_id.
    $input = ['icon_id' => ''];
    $element['#default_value'] = 'foo';

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);
    $this->assertSame([], $actual);
  }

  /**
   * Test the valueCallback method with no Icon.
   */
  public function testValueCallbackInvalidIcon(): void {
    $element = [];

    $input = [
      'icon_id' => 'foo:bar',
      'icon_settings' => ['foo' => 'bar'],
    ];

    $form_state = $this->createMock(FormStateInterface::class);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->willReturn(NULL);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);
    $this->assertSame($input, $actual);
  }

  /**
   * Test the valueCallback method with no callback.
   */
  public function testValueCallbackNoCallback(): void {
    $element = [];

    $input = FALSE;

    $form_state = $this->createMock(FormStateInterface::class);

    $ui_icon_pack_plugin_manager = $this->createMock(IconPackManagerInterface::class);
    $ui_icon_pack_plugin_manager->method('getIcon')
      ->willReturn(NULL);
    $this->container->set('plugin.manager.icon_pack', $ui_icon_pack_plugin_manager);

    $actual = IconAutocomplete::valueCallback($element, $input, $form_state);
    $this->assertSame($input, $actual);
  }

  /**
   * Test the buildAjaxCallback method.
   */
  public function testBuildAjaxCallback(): void {
    $form = [
      'foo' => [
        '#prefix' => '',
        '#attached' => ['foo/bar'],
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    $request = new Request(['element_parents' => 'foo']);

    $prophecy = $this->prophesize(RendererInterface::class);
    $prophecy->renderRoot(Argument::any())->willReturn('_rendered_');
    $renderer = $prophecy->reveal();
    $this->container->set('renderer', $renderer);

    $actual = IconAutocomplete::buildAjaxCallback($form, $form_state, $request);

    $expected = [
      'command' => 'insert',
      'method' => 'replaceWith',
      'selector' => NULL,
      'data' => '_rendered_',
      'settings' => NULL,
    ];
    $this->assertSame([$expected], $actual->getCommands());
  }

  /**
   * Create a mock icon.
   *
   * @param array<string, string>|null $iconData
   *   The icon data to create.
   *
   * @return \Drupal\Core\Theme\Icon\IconDefinitionInterface
   *   The icon mocked.
   */
  private function createMockIcon(?array $iconData = NULL): IconDefinitionInterface {
    if (NULL === $iconData) {
      $iconData = [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
      ];
    }

    $icon = $this->prophesize(IconDefinitionInterface::class);
    $icon
      ->getRenderable(['width' => $iconData['width'] ?? '', 'height' => $iconData['height'] ?? ''])
      ->willReturn(['#markup' => '<svg></svg>']);

    $icon_full_id = IconDefinition::createIconId($iconData['pack_id'], $iconData['icon_id']);
    $icon
      ->getId()
      ->willReturn($icon_full_id);

    return $icon->reveal();
  }

}
