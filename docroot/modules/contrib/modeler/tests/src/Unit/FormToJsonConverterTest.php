<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\modeler\FormToJsonConverter;
use Drupal\modeler\YamlSchemaLookup;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the conversion of Drupal form arrays to a JSON-serializable format.
 *
 * @coversDefaultClass \Drupal\modeler\FormToJsonConverter
 *
 * @group modeler
 */
#[Group('modeler')]
class FormToJsonConverterTest extends UnitTestCase {

  /**
   * The converter under test.
   *
   * @var \Drupal\modeler\FormToJsonConverter
   */
  protected FormToJsonConverter $converter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The YAML schema lookup is only exercised for textarea fields with a
    // non-empty plugin schema key; the unit tests use an empty key, so a bare
    // mock with no expectations is sufficient.
    $yaml_schema_lookup = $this->createStub(YamlSchemaLookup::class);
    $typed_config_manager = $this->createStub(TypedConfigManagerInterface::class);

    $this->converter = new FormToJsonConverter($yaml_schema_lookup, $typed_config_manager);
    // Provide a translation stub so StringTranslationTrait::t() works without
    // a Drupal container bootstrap.
    $this->converter->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that #states are normalized with selectors simplified to field keys.
   *
   * @covers ::convert
   * @covers ::extractStates
   * @covers ::simplifySelector
   */
  public function testStatesNormalization(): void {
    $form = [
      'use_yaml' => [
        '#type' => 'checkbox',
        '#title' => 'Use YAML',
      ],
      'validate_yaml' => [
        '#type' => 'checkbox',
        '#title' => 'Validate YAML',
        '#states' => [
          'visible' => [
            ':input[name="use_yaml"]' => ['checked' => TRUE],
          ],
        ],
      ],
      'advanced' => [
        '#type' => 'textfield',
        '#title' => 'Advanced',
        '#states' => [
          'invisible' => [
            ':input[name="mode[selector]"]' => ['value' => 'advanced'],
          ],
          'required' => [
            ':input[name="use_yaml"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    // The use_yaml checkbox has no states.
    $this->assertArrayNotHasKey('states', $result[0]);

    // validate_yaml: a single "visible" condition simplified to the bare key.
    // Each state type is a list of OR groups; each group is an AND list of
    // conditions. A single simple condition yields one group with one
    // condition.
    $this->assertSame([
      'visible' => [
        [['field' => 'use_yaml', 'checked' => TRUE]],
      ],
    ], $result[1]['states']);

    // advanced: invisible (value) + required (checked); the array-name
    // selector "mode[selector]" is simplified to "mode".
    $this->assertSame([
      'invisible' => [
        [['field' => 'mode', 'value' => 'advanced']],
      ],
      'required' => [
        [['field' => 'use_yaml', 'checked' => TRUE]],
      ],
    ], $result[2]['states']);
  }

  /**
   * Tests that the Drupal OR-form #states normalize to multiple OR groups.
   *
   * The OR form places a numerically-indexed list of condition arrays under a
   * single selector, meaning "match ANY" (logical OR). This mirrors the ECA
   * LoadEntity 'properties' textarea, which is visible when
   * (from == 'properties') OR (from == '_eca_token').
   *
   * @covers ::convert
   * @covers ::extractStates
   * @covers ::simplifySelector
   */
  public function testOrFormStatesNormalization(): void {
    $form = [
      'properties' => [
        '#type' => 'textarea',
        '#title' => 'Properties',
        '#states' => [
          'visible' => [
            ':input[name="from"]' => [
              ['value' => 'properties'],
              ['value' => '_eca_token'],
            ],
          ],
          'required' => [
            ':input[name="from"]' => ['value' => 'properties'],
          ],
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    // The OR form yields two OR groups, each a single-condition AND group:
    // (from == 'properties') OR (from == '_eca_token').
    $this->assertSame([
      'visible' => [
        [['field' => 'from', 'value' => 'properties']],
        [['field' => 'from', 'value' => '_eca_token']],
      ],
      'required' => [
        [['field' => 'from', 'value' => 'properties']],
      ],
    ], $result[0]['states']);
  }

  /**
   * Tests that an array "value" normalizes to match-any OR groups.
   *
   * Drupal's #states allow the "value" key itself to be an array of scalars,
   * meaning "equals any listed value". This expands to one OR group per value.
   *
   * @covers ::convert
   * @covers ::extractStates
   */
  public function testArrayValueStatesNormalization(): void {
    $form = [
      'field' => [
        '#type' => 'textfield',
        '#title' => 'Field',
        '#states' => [
          'visible' => [
            ':input[name="mode"]' => ['value' => ['a', 'b']],
          ],
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    // The array value expands into two OR groups:
    // (mode == 'a') OR (mode == 'b').
    $this->assertSame([
      'visible' => [
        [['field' => 'mode', 'value' => 'a']],
        [['field' => 'mode', 'value' => 'b']],
      ],
    ], $result[0]['states']);
  }

  /**
   * Tests that multiple selectors in one state type combine as an AND group.
   *
   * Multiple selector entries within one state type combine with logical AND,
   * producing a single OR group containing all conditions.
   *
   * @covers ::convert
   * @covers ::extractStates
   */
  public function testMultipleSelectorsAndGroup(): void {
    $form = [
      'field' => [
        '#type' => 'textfield',
        '#title' => 'Field',
        '#states' => [
          'visible' => [
            ':input[name="mode"]' => ['value' => 'advanced'],
            ':input[name="enabled"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    // Two selectors in one state type => one AND group with two conditions.
    $this->assertSame([
      'visible' => [
        [
          ['field' => 'mode', 'value' => 'advanced'],
          ['field' => 'enabled', 'checked' => TRUE],
        ],
      ],
    ], $result[0]['states']);
  }

  /**
   * Tests that an AND selector combined with an OR-list expands via product.
   *
   * When one state type mixes a simple selector (AND) with an OR-list selector,
   * Drupal semantics are A AND (B1 OR B2) = (A AND B1) OR (A AND B2). The
   * normalizer produces the Cartesian product of OR alternatives across
   * selectors.
   *
   * @covers ::convert
   * @covers ::extractStates
   */
  public function testAndWithOrListExpandsToProduct(): void {
    $form = [
      'field' => [
        '#type' => 'textfield',
        '#title' => 'Field',
        '#states' => [
          'visible' => [
            ':input[name="enabled"]' => ['checked' => TRUE],
            ':input[name="from"]' => [
              ['value' => 'properties'],
              ['value' => '_eca_token'],
            ],
          ],
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    // (enabled checked) AND (from == 'properties' OR from == '_eca_token')
    // => two OR groups, each carrying the shared AND condition.
    $this->assertSame([
      'visible' => [
        [
          ['field' => 'enabled', 'checked' => TRUE],
          ['field' => 'from', 'value' => 'properties'],
        ],
        [
          ['field' => 'enabled', 'checked' => TRUE],
          ['field' => 'from', 'value' => '_eca_token'],
        ],
      ],
    ], $result[0]['states']);
  }

  /**
   * Tests that nested container elements emit a group with ordered children.
   *
   * @covers ::convert
   * @covers ::convertElement
   */
  public function testNestedGroupConversion(): void {
    $form = [
      'wrapper' => [
        '#type' => 'details',
        '#title' => 'Wrapper',
        '#open' => FALSE,
        // Children intentionally declared out of #weight order to verify that
        // Element::children(..., TRUE) sorts by weight.
        'second' => [
          '#type' => 'textfield',
          '#title' => 'Second',
          '#weight' => 10,
        ],
        'first' => [
          '#type' => 'textfield',
          '#title' => 'First',
          '#weight' => 0,
        ],
      ],
      'fieldset_wrapper' => [
        '#type' => 'fieldset',
        '#title' => 'Fieldset',
        'inner' => [
          '#type' => 'checkbox',
          '#title' => 'Inner',
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    // The details element becomes a group carrying its open state.
    $this->assertSame('group', $result[0]['type']);
    $this->assertSame('Wrapper', $result[0]['title']);
    $this->assertFalse($result[0]['open']);
    $this->assertCount(2, $result[0]['children']);
    // Children are ordered by weight: first (0) then second (10).
    $this->assertSame('first', $result[0]['children'][0]['key']);
    $this->assertSame('First', $result[0]['children'][0]['title']);
    $this->assertSame('second', $result[0]['children'][1]['key']);

    // The fieldset element becomes a group without an "open" flag.
    $this->assertSame('group', $result[1]['type']);
    $this->assertArrayNotHasKey('open', $result[1]);
    $this->assertCount(1, $result[1]['children']);
    $this->assertSame('inner', $result[1]['children'][0]['key']);
    $this->assertSame('checkbox', $result[1]['children'][0]['type']);
  }

  /**
   * Tests that a plain textfield still emits the existing flat field shape.
   *
   * @covers ::convert
   * @covers ::convertElement
   */
  public function testPlainTextfieldRegression(): void {
    $form = [
      'label' => [
        '#type' => 'textfield',
        '#title' => 'Label',
        '#description' => 'The label.',
        '#required' => TRUE,
        '#default_value' => 'Hello',
      ],
    ];

    $result = $this->converter->convert($form);

    $this->assertCount(1, $result);
    $field = $result[0];
    $this->assertSame('label', $field['key']);
    $this->assertSame('textfield', $field['type']);
    $this->assertSame('Label', $field['title']);
    $this->assertSame('The label.', $field['description']);
    $this->assertTrue($field['required']);
    $this->assertSame('Hello', $field['default_value']);
    $this->assertFalse($field['token_support']);
    // A plain textfield with no #states must not carry a "states" key.
    $this->assertArrayNotHasKey('states', $field);
    // Nor a "children" key (it is not a group).
    $this->assertArrayNotHasKey('children', $field);
  }

  /**
   * Tests that a required select promotes an already-merged empty option.
   *
   * @covers ::convert
   * @covers ::extractEmptyOption
   */
  public function testRequiredSelectPromotesMergedEmptyOption(): void {
    // A required select with no #default_value: core's processSelect() has
    // already prepended ['' => '- Select -'] into #options.
    $form = [
      'choice' => [
        '#type' => 'select',
        '#title' => 'Choice',
        '#required' => TRUE,
        '#options' => [
          '' => '- Select -',
          'a' => 'Option A',
          'b' => 'Option B',
        ],
      ],
    ];

    $result = $this->converter->convert($form);
    $field = $result[0];

    $this->assertSame(['value' => '', 'label' => '- Select -'], $field['empty_option']);
    // The promoted entry is removed from the emitted options.
    $this->assertArrayNotHasKey('', $field['options']);
    $this->assertSame(['a' => 'Option A', 'b' => 'Option B'], $field['options']);
  }

  /**
   * Tests that an explicit #empty_option label is preserved when merged.
   *
   * @covers ::convert
   * @covers ::extractEmptyOption
   */
  public function testSelectPromotesExplicitEmptyOptionLabel(): void {
    // A select with an explicit #empty_option: core has merged
    // ['' => 'Pick one'] into #options.
    $form = [
      'choice' => [
        '#type' => 'select',
        '#title' => 'Choice',
        '#empty_option' => 'Pick one',
        '#options' => [
          '' => 'Pick one',
          'a' => 'Option A',
        ],
      ],
    ];

    $result = $this->converter->convert($form);
    $field = $result[0];

    $this->assertSame('Pick one', $field['empty_option']['label']);
    $this->assertSame('', $field['empty_option']['value']);
    $this->assertArrayNotHasKey('', $field['options']);
    $this->assertSame(['a' => 'Option A'], $field['options']);
  }

  /**
   * Tests that a #multiple select never emits an empty option.
   *
   * @covers ::convert
   * @covers ::extractEmptyOption
   */
  public function testMultipleSelectHasNoEmptyOption(): void {
    $form = [
      'choice' => [
        '#type' => 'select',
        '#title' => 'Choice',
        '#multiple' => TRUE,
        '#required' => TRUE,
        '#options' => [
          'a' => 'Option A',
          'b' => 'Option B',
        ],
      ],
    ];

    $result = $this->converter->convert($form);
    $field = $result[0];

    $this->assertArrayNotHasKey('empty_option', $field);
    // Options are emitted unchanged.
    $this->assertSame(['a' => 'Option A', 'b' => 'Option B'], $field['options']);
  }

  /**
   * Tests that a non-required select with no empty hints gets no empty option.
   *
   * @covers ::convert
   * @covers ::extractEmptyOption
   */
  public function testNonRequiredSelectHasNoEmptyOption(): void {
    $form = [
      'choice' => [
        '#type' => 'select',
        '#title' => 'Choice',
        '#options' => [
          'a' => 'Option A',
          'b' => 'Option B',
        ],
      ],
    ];

    $result = $this->converter->convert($form);
    $field = $result[0];

    $this->assertArrayNotHasKey('empty_option', $field);
    $this->assertSame(['a' => 'Option A', 'b' => 'Option B'], $field['options']);
  }

  /**
   * Tests that elements denied access are dropped at any nesting depth.
   *
   * A model owner hides fields it manages itself by setting #access to FALSE
   * in modelConfigFormAlter(). Such a field must not reach the UI at all, as
   * rendering it would let the user edit a value the owner overrules anyway.
   *
   * @covers ::convert
   * @covers ::convertElement
   */
  public function testAccessFalseElementsAreDropped(): void {
    $form = [
      'label' => [
        '#type' => 'textfield',
        '#title' => 'Label',
        '#access' => FALSE,
      ],
      'visible_field' => [
        '#type' => 'textfield',
        '#title' => 'Visible',
      ],
      'hidden_group' => [
        '#type' => 'details',
        '#title' => 'Hidden group',
        '#access' => FALSE,
        'inner' => [
          '#type' => 'textfield',
          '#title' => 'Inner',
        ],
      ],
      'advanced' => [
        '#type' => 'details',
        '#title' => 'Advanced',
        'kept' => [
          '#type' => 'textfield',
          '#title' => 'Kept',
        ],
        'dropped' => [
          '#type' => 'textfield',
          '#title' => 'Dropped',
          '#access' => FALSE,
        ],
      ],
    ];

    $result = $this->converter->convert($form);

    $this->assertSame(['visible_field', 'advanced'], array_column($result, 'key'));
    $this->assertSame(['kept'], array_column($result[1]['children'], 'key'));
  }

  /**
   * Tests that #disabled and #maxlength round-trip into the field.
   *
   * @covers ::convert
   * @covers ::convertElement
   */
  public function testDisabledAndMaxlengthAreEmitted(): void {
    $form = [
      'summary' => [
        '#type' => 'textfield',
        '#title' => 'Summary',
        '#maxlength' => 255,
      ],
      'storage' => [
        '#type' => 'select',
        '#title' => 'Storage',
        '#options' => ['a' => 'Option A'],
        '#disabled' => TRUE,
      ],
      'plain' => [
        '#type' => 'textfield',
        '#title' => 'Plain',
        '#disabled' => FALSE,
      ],
    ];

    $result = $this->converter->convert($form);

    $this->assertSame(255, $result[0]['maxlength']);
    $this->assertArrayNotHasKey('disabled', $result[0]);
    $this->assertTrue($result[1]['disabled']);
    // An element that is not disabled must not carry the key at all, so the
    // UI never has to distinguish FALSE from absent.
    $this->assertArrayNotHasKey('disabled', $result[2]);
    $this->assertArrayNotHasKey('maxlength', $result[2]);
  }

  /**
   * Tests that a machine_name element exposes its source and title.
   *
   * Core's machine_name element carries no #title of its own; the label lives
   * in the #machine_name array, together with the source field the UI derives
   * the machine name from.
   *
   * @covers ::convert
   * @covers ::convertElement
   */
  public function testMachineNameEmitsSourceAndTitleFallback(): void {
    $form = [
      'model_id' => [
        '#type' => 'machine_name',
        '#disabled' => TRUE,
        '#machine_name' => [
          'exists' => 'some_callback',
          'source' => ['label'],
          'label' => 'Model ID',
        ],
      ],
      'titled_id' => [
        '#type' => 'machine_name',
        '#title' => 'Explicit title',
        '#machine_name' => [
          'source' => ['wrapper', 'name'],
          'label' => 'Ignored label',
        ],
      ],
      'bare_id' => [
        '#type' => 'machine_name',
        '#machine_name' => ['exists' => 'some_callback'],
      ],
    ];

    $result = $this->converter->convert($form);

    $this->assertSame('Model ID', $result[0]['title']);
    $this->assertSame('label', $result[0]['source']);
    $this->assertTrue($result[0]['disabled']);

    // An explicit #title wins over the #machine_name label, and a nested
    // source path is reduced to the field key the UI can observe.
    $this->assertSame('Explicit title', $result[1]['title']);
    $this->assertSame('name', $result[1]['source']);

    // Without a source there is nothing to derive from, and the key is the
    // last title fallback.
    $this->assertSame('bare_id', $result[2]['title']);
    $this->assertArrayNotHasKey('source', $result[2]);
  }

}
