<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\eca\Plugin\FormFieldMachineName;
use Drupal\eca\PluginManager\Action as EcaActionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests asserting form and schema agree on machine-name-like fields.
 *
 * Every plugin field validated by
 * \Drupal\eca\Plugin\FormFieldMachineName::validateElementsMachineName()
 * implements exactly one of three contracts, selected by the flags on the form
 * element. The configuration schema has to express the very same contract, so
 * that a value the user interface accepts also survives configuration
 * validation, and a value the user interface rejects is rejected by
 * configuration validation too.
 *
 * The tests below drive both sides over one shared set of values and assert
 * three things:
 * - Both sides reach the same verdict (the actual acceptance criterion).
 * - That verdict is the intended one. Mutual agreement alone would also be
 *   satisfied by two sides that are both wrong in the same way.
 * - The live form element still carries the flags and required state this
 *   classification is built on, so that a future change to a plugin form makes
 *   this test fail instead of silently drifting away from the schema.
 *
 * @see \Drupal\eca\Plugin\FormFieldMachineName
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaElementsMachineNameConstraint
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class ElementsMachineNameContractTest extends RenderActionsTestBase {

  /**
   * Contract accepting characters, numbers, hyphens and underscores.
   */
  protected const string CONTRACT_MACHINE_NAME = 'machine_name';

  /**
   * Contract additionally accepting colons, to reference a token.
   */
  protected const string CONTRACT_TOKEN_REFERENCE = 'token_reference';

  /**
   * Contract additionally accepting brackets, to hold tokens and nested paths.
   */
  protected const string CONTRACT_TOKEN_REPLACEMENT = 'token_replacement';

  /**
   * The configuration schema type that expresses each contract.
   */
  protected const array SCHEMA_TYPES = [
    self::CONTRACT_MACHINE_NAME => 'eca.machine_name',
    self::CONTRACT_TOKEN_REFERENCE => 'eca.token_reference',
    self::CONTRACT_TOKEN_REPLACEMENT => 'eca.token_replaced_machine_name',
  ];

  /**
   * Tests that form and schema agree on every non-empty value.
   *
   * @param string $schema_type
   *   The configuration schema type owning the field.
   * @param string $field
   *   The configuration key of the field.
   * @param string $contract
   *   The contract the field implements, one of the CONTRACT_* constants.
   * @param bool $required
   *   Whether the form element is required.
   */
  #[DataProvider('contractFieldProvider')]
  public function testFormAndSchemaAgree(string $schema_type, string $field, string $contract, bool $required): void {
    foreach (self::valueProvider() as $label => $expectations) {
      $value = $expectations['value'];
      if ($value === '') {
        // The required state is asserted separately, see testEmptyValue().
        continue;
      }
      $expected_accepted = $expectations[$contract];

      $form_accepted = $this->formAccepts($value, $contract);
      $schema_accepted = $this->schemaAccepts($schema_type, $field, $value);

      $this->assertSame($expected_accepted, $form_accepted, sprintf('The form validator verdict on "%s" for %s:%s (%s) is unexpected.', $label, $schema_type, $field, $contract));
      $this->assertSame($expected_accepted, $schema_accepted, sprintf('The schema verdict on "%s" for %s:%s (%s) is unexpected.', $label, $schema_type, $field, $contract));
      $this->assertSame($form_accepted, $schema_accepted, sprintf('The form validator and the schema disagree on "%s" for %s:%s.', $label, $schema_type, $field));
    }
  }

  /**
   * Tests that the empty value is governed by the required state alone.
   *
   * The form validator accepts the empty string for every contract, because a
   * required form element is enforced by Drupal's own required validation and
   * not by the element validator. The schema expresses the same split: the
   * pattern accepts the empty string, and only an additional "NotBlank"
   * constraint rejects it.
   *
   * @param string $schema_type
   *   The configuration schema type owning the field.
   * @param string $field
   *   The configuration key of the field.
   * @param string $contract
   *   The contract the field implements, one of the CONTRACT_* constants.
   * @param bool $required
   *   Whether the form element is required.
   */
  #[DataProvider('contractFieldProvider')]
  public function testEmptyValue(string $schema_type, string $field, string $contract, bool $required): void {
    $this->assertTrue($this->formAccepts('', $contract), sprintf('The form validator rejects the empty value for %s:%s.', $schema_type, $field));
    $this->assertSame(!$required, $this->schemaAccepts($schema_type, $field, ''), sprintf('The required state in the schema of %s:%s does not match the form element.', $schema_type, $field));
  }

  /**
   * Tests that the live form elements still match this classification.
   *
   * @param string $schema_type
   *   The configuration schema type owning the field.
   * @param string $field
   *   The configuration key of the field.
   * @param string $contract
   *   The contract the field implements, one of the CONTRACT_* constants.
   * @param bool $required
   *   Whether the form element is required.
   * @param string $plugin_id
   *   The plugin ID to build the configuration form of.
   * @param string $plugin_type
   *   Either "action" or "event".
   */
  #[DataProvider('fieldProvider')]
  public function testFormElementContract(string $schema_type, string $field, string $contract, bool $required, string $plugin_id, string $plugin_type): void {
    $manager = $plugin_type === 'event' ? \Drupal::service('plugin.manager.eca.event') : $this->actionManager;
    $plugin = $manager->createInstance($plugin_id);
    $element = $plugin->buildConfigurationForm([], new FormState())[$field] ?? NULL;

    $this->assertIsArray($element, sprintf('The plugin "%s" does not build a form element for "%s".', $plugin_id, $field));
    $this->assertSame([[FormFieldMachineName::class, 'validateElementsMachineName']], $element['#element_validate'] ?? NULL, sprintf('The form element %s:%s is not validated as a machine name.', $plugin_id, $field));
    $this->assertSame($contract, $this->contractOfElement($element), sprintf('The form element %s:%s implements another contract than the schema declares.', $plugin_id, $field));
    $this->assertSame($required, ($element['#required'] ?? FALSE) === TRUE, sprintf('The form element %s:%s is required differently than the schema declares.', $plugin_id, $field));
  }

  /**
   * Tests that the schema follows every live machine-name-like form element.
   *
   * The data provider pins down the classification of the fields that exist
   * today, which is what makes a changed plugin form visible. It cannot notice
   * a field that is added tomorrow though, and a machine-name-like field left
   * untyped in schema is exactly the drift this alignment is meant to end. So
   * this test does not consult the provider at all: it walks the plugins, picks
   * out every element that is validated as a machine name, and demands that the
   * configuration schema expresses that element's contract and required state.
   *
   * Only the direct children of a plugin form are looked at, because those are
   * the elements whose key is also the configuration key.
   *
   * All fields implementing the contract are provided by this module today, so
   * the plugins reachable from here are all of them. Should another ECA
   * submodule start using the contract, this test has to move to where that
   * submodule is enabled as well.
   */
  public function testSchemaFollowsEveryFormElement(): void {
    $elements = $this->collectMachineNameElements();

    // Two safety nets, because a sweep that collects nothing would assert
    // nothing while still passing.
    //
    // The first one is a floor on the sheer number of elements. It stands at
    // 50 today and is deliberately checked loosely, so that adding or removing
    // a single plugin field does not fail a test that is not about counting.
    $this->assertGreaterThan(40, count($elements), 'The sweep collected far fewer elements than the 50 that exist, so it is no longer looking everywhere.');

    // The second one is what actually pins the sweep down: every field the data
    // provider knows about has to turn up in it. A narrowing of the sweep is
    // then reported as the list of fields that went missing, instead of as a
    // number that says nothing about the cause.
    $collected = [];
    foreach ($elements as [$schema_type, $field]) {
      $collected[$schema_type . ':' . $field] = TRUE;
    }
    $missing = [];
    foreach (self::fieldProvider() as $label => [$schema_type, $field]) {
      if (!isset($collected[$schema_type . ':' . $field])) {
        $missing[] = sprintf('%s (%s:%s)', $label, $schema_type, $field);
      }
    }
    $this->assertSame([], $missing, 'The sweep did not reach fields that are known to implement the contract.');

    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $this->container->get('config.typed');
    $errors = [];
    foreach ($elements as [$schema_type, $field, $contract, $required, $plugin_id]) {
      $label = sprintf('%s:%s (%s)', $plugin_id, $field, $schema_type);
      $definition = $typed_config_manager->getDefinition($schema_type, FALSE);
      $mapping = $definition['mapping'][$field] ?? NULL;
      if ($mapping === NULL) {
        $errors[] = sprintf('%s is not described by the configuration schema.', $label);
        continue;
      }
      $expected_type = self::SCHEMA_TYPES[$contract];
      if (($mapping['type'] ?? 'undefined') !== $expected_type) {
        $errors[] = sprintf('%s must be typed "%s", found "%s".', $label, $expected_type, $mapping['type'] ?? 'undefined');
      }
      // The required state is asserted on the key itself, because that is where
      // three types deliberately leave it open.
      $not_blank = array_key_exists('NotBlank', $mapping['constraints'] ?? []);
      if ($required && !$not_blank) {
        $errors[] = sprintf('%s is a required form element, so its schema key needs a "NotBlank" constraint.', $label);
      }
      if (!$required && $not_blank) {
        $errors[] = sprintf('%s is not a required form element, so its schema key must not carry a "NotBlank" constraint.', $label);
      }
    }
    $this->assertSame([], $errors);
  }

  /**
   * Collects every form element that is validated as a machine name.
   *
   * The action plugins are listed through the manager that ECA decorates, not
   * through "plugin.manager.action" itself. ECA replaces that service with
   * \Drupal\eca\PluginManager\Action, whose getDefinitions() hides every action
   * that is not externally available, which is almost all of ECA's own actions.
   * Listing the decorated manager instead is what ECA does internally too, and
   * it is the only way to see the actions this test is about. Note that the
   * omission is invisible from createInstance(), which the decorator forwards
   * unfiltered, so a sweep over the wrong manager silently finds next to
   * nothing rather than failing.
   *
   * @return array<int, array{string, string, string, bool, string}>
   *   A list of elements, each of them holding the configuration schema type,
   *   the configuration key, the contract, whether the element is required, and
   *   the plugin ID it belongs to.
   */
  protected function collectMachineNameElements(): array {
    $managers = [
      'action.configuration.' => EcaActionManager::get()->getDecoratedActionManager(),
      'eca.event.plugin.' => \Drupal::service('plugin.manager.eca.event'),
      'eca.condition.plugin.' => \Drupal::service('plugin.manager.eca.condition'),
    ];

    $elements = [];
    foreach ($managers as $prefix => $manager) {
      foreach (array_keys($manager->getDefinitions()) as $plugin_id) {
        try {
          $plugin = $manager->createInstance($plugin_id);
          if (!($plugin instanceof PluginFormInterface)) {
            continue;
          }
          $form = $plugin->buildConfigurationForm([], new FormState());
        }
        catch (\Throwable) {
          // A plugin that cannot be instantiated or built here contributes
          // nothing to look at. The assertion on the number of collected
          // elements is what notices if this ever stops working wholesale.
          continue;
        }
        foreach (Element::children($form) as $field) {
          $element = $form[$field];
          $validators = is_array($element) ? ($element['#element_validate'] ?? []) : [];
          if (!in_array([FormFieldMachineName::class, 'validateElementsMachineName'], $validators, TRUE)) {
            continue;
          }
          $elements[] = [
            $prefix . $plugin_id,
            (string) $field,
            $this->contractOfElement($element),
            ($element['#required'] ?? FALSE) === TRUE,
            (string) $plugin_id,
          ];
        }
      }
    }
    return $elements;
  }

  /**
   * Determines the contract a form element implements from its flags.
   *
   * @param array $element
   *   The form element.
   *
   * @return string
   *   One of the CONTRACT_* constants.
   */
  protected function contractOfElement(array $element): string {
    if ($element['#eca_token_reference'] ?? FALSE) {
      return self::CONTRACT_TOKEN_REFERENCE;
    }
    if ($element['#eca_token_replacement'] ?? FALSE) {
      return self::CONTRACT_TOKEN_REPLACEMENT;
    }
    return self::CONTRACT_MACHINE_NAME;
  }

  /**
   * Runs the form element validator and reports whether it accepted the value.
   *
   * @param string $value
   *   The value to validate.
   * @param string $contract
   *   The contract to validate against, one of the CONTRACT_* constants.
   *
   * @return bool
   *   TRUE if the validator raised no error.
   */
  protected function formAccepts(string $value, string $contract): bool {
    $element = [
      '#type' => 'textfield',
      '#title' => 'Machine name',
      '#parents' => ['field'],
      '#value' => $value,
    ];
    if ($contract === self::CONTRACT_TOKEN_REFERENCE) {
      $element['#eca_token_reference'] = TRUE;
    }
    if ($contract === self::CONTRACT_TOKEN_REPLACEMENT) {
      $element['#eca_token_replacement'] = TRUE;
    }
    $form_state = new FormState();
    FormFieldMachineName::validateElementsMachineName($element, $form_state);
    return $form_state->getErrors() === [];
  }

  /**
   * Validates a value against the configuration schema of a single field.
   *
   * @param string $schema_type
   *   The configuration schema type owning the field.
   * @param string $field
   *   The configuration key of the field.
   * @param string $value
   *   The value to validate.
   *
   * @return bool
   *   TRUE if the schema raised no violation for that field.
   */
  protected function schemaAccepts(string $schema_type, string $field, string $value): bool {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $this->container->get('config.typed');
    $definition = $typed_config_manager->getDefinition($schema_type);
    $this->assertArrayHasKey($field, $definition['mapping'] ?? [], sprintf('The schema type "%s" does not define "%s".', $schema_type, $field));

    $data = [$field => $value];
    $data_definition = $typed_config_manager->buildDataDefinition($definition, $data);
    $element = $typed_config_manager->create($data_definition, $data);

    foreach ($element->validate() as $violation) {
      if ($violation->getPropertyPath() === $field) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Provides every plugin field that is validated as a machine name.
   *
   * @return array<string, array{string, string, string, bool, string, string}>
   *   The test cases, keyed by a human readable description.
   */
  public static function fieldProvider(): array {
    return [
      'render event: extra field name' => [
        'eca.event.plugin.eca_render:extra_field', 'extra_field_name',
        self::CONTRACT_MACHINE_NAME, TRUE, 'eca_render:extra_field', 'event',
      ],
      'entity view field: field name' => [
        'action.configuration.eca_render_entity_view_field', 'field_name',
        self::CONTRACT_TOKEN_REPLACEMENT, TRUE, 'eca_render_entity_view_field', 'action',
      ],
      'entity view field: view mode' => [
        'action.configuration.eca_render_entity_view_field', 'view_mode',
        self::CONTRACT_TOKEN_REPLACEMENT, FALSE, 'eca_render_entity_view_field', 'action',
      ],
      'entity form: operation' => [
        'action.configuration.eca_render_entity_form', 'operation',
        self::CONTRACT_TOKEN_REPLACEMENT, TRUE, 'eca_render_entity_form', 'action',
      ],
      'get active theme: token name' => [
        'action.configuration.eca_get_active_theme', 'token_name',
        self::CONTRACT_TOKEN_REFERENCE, TRUE, 'eca_get_active_theme', 'action',
      ],
      'dropbutton: dropbutton type' => [
        'action.configuration.eca_render_dropbutton', 'dropbutton_type',
        self::CONTRACT_MACHINE_NAME, FALSE, 'eca_render_dropbutton', 'action',
      ],
      'render element base: name' => [
        'action.configuration.eca_render_markup', 'name',
        self::CONTRACT_TOKEN_REPLACEMENT, FALSE, 'eca_render_markup', 'action',
      ],
      'render element base: token name' => [
        'action.configuration.eca_render_markup', 'token_name',
        self::CONTRACT_TOKEN_REFERENCE, FALSE, 'eca_render_markup', 'action',
      ],
      'custom form: custom form ID' => [
        'action.configuration.eca_render_custom_form', 'custom_form_id',
        self::CONTRACT_MACHINE_NAME, TRUE, 'eca_render_custom_form', 'action',
      ],
      'set weight: name' => [
        'action.configuration.eca_render_set_weight', 'name',
        self::CONTRACT_TOKEN_REPLACEMENT, TRUE, 'eca_render_set_weight', 'action',
      ],
      'add class: name' => [
        'action.configuration.eca_render_add_class', 'name',
        self::CONTRACT_TOKEN_REPLACEMENT, TRUE, 'eca_render_add_class', 'action',
      ],
      'add attached library: name' => [
        'action.configuration.eca_render_add_attached_library', 'name',
        self::CONTRACT_TOKEN_REPLACEMENT, FALSE, 'eca_render_add_attached_library', 'action',
      ],
      'add attached setting: name' => [
        'action.configuration.eca_render_add_attached_setting', 'name',
        self::CONTRACT_TOKEN_REPLACEMENT, FALSE, 'eca_render_add_attached_setting', 'action',
      ],
      'image: style name' => [
        'action.configuration.eca_render_image:image', 'style_name',
        self::CONTRACT_TOKEN_REPLACEMENT, FALSE, 'eca_render_image:image', 'action',
      ],
      'responsive image: style name' => [
        'action.configuration.eca_render_responsive_image:responsive_image', 'style_name',
        self::CONTRACT_TOKEN_REPLACEMENT, TRUE, 'eca_render_responsive_image:responsive_image', 'action',
      ],
      'entity view: view mode' => [
        'action.configuration.eca_render_entity_view', 'view_mode',
        self::CONTRACT_TOKEN_REPLACEMENT, TRUE, 'eca_render_entity_view', 'action',
      ],
    ];
  }

  /**
   * Provides the contract data used without plugin form metadata.
   *
   * @return array<string, array{string, string, string, bool}>
   *   The test cases, keyed by a human readable description.
   */
  public static function contractFieldProvider(): array {
    return array_map(
      static fn(array $field): array => array_slice($field, 0, 4),
      self::fieldProvider(),
    );
  }

  /**
   * Provides values and whether each contract accepts them.
   *
   * @return array<string, array{value: string, machine_name: bool, token_reference: bool, token_replacement: bool}>
   *   The values, keyed by a human readable description.
   */
  public static function valueProvider(): array {
    return [
      'plain machine name' => [
        'value' => 'details_title',
        self::CONTRACT_MACHINE_NAME => TRUE,
        self::CONTRACT_TOKEN_REFERENCE => TRUE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
      'mixed case and hyphen' => [
        'value' => 'Details-Title',
        self::CONTRACT_MACHINE_NAME => TRUE,
        self::CONTRACT_TOKEN_REFERENCE => TRUE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
      'colon separated' => [
        'value' => 'foo:bar',
        self::CONTRACT_MACHINE_NAME => FALSE,
        self::CONTRACT_TOKEN_REFERENCE => TRUE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
      'nested render array path' => [
        'value' => 'details][title',
        self::CONTRACT_MACHINE_NAME => FALSE,
        self::CONTRACT_TOKEN_REFERENCE => FALSE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
      'token' => [
        'value' => '[entity:value]',
        self::CONTRACT_MACHINE_NAME => FALSE,
        self::CONTRACT_TOKEN_REFERENCE => FALSE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
      'nested path holding a token' => [
        'value' => 'details][[entity:value]',
        self::CONTRACT_MACHINE_NAME => FALSE,
        self::CONTRACT_TOKEN_REFERENCE => FALSE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
      'whitespace' => [
        'value' => 'details title',
        self::CONTRACT_MACHINE_NAME => FALSE,
        self::CONTRACT_TOKEN_REFERENCE => FALSE,
        self::CONTRACT_TOKEN_REPLACEMENT => FALSE,
      ],
      'period' => [
        'value' => 'details.title',
        self::CONTRACT_MACHINE_NAME => FALSE,
        self::CONTRACT_TOKEN_REFERENCE => FALSE,
        self::CONTRACT_TOKEN_REPLACEMENT => FALSE,
      ],
      'empty' => [
        'value' => '',
        self::CONTRACT_MACHINE_NAME => TRUE,
        self::CONTRACT_TOKEN_REFERENCE => TRUE,
        self::CONTRACT_TOKEN_REPLACEMENT => TRUE,
      ],
    ];
  }

}
