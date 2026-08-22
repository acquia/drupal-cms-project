<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that tokenized numeric plugin configuration survives being saved.
 *
 * Numeric configuration keys of ECA plugins are token capable: the form element
 * is a textfield flagged with "#eca_token_replacement", and the plugin resolves
 * the token at runtime. Storing such a value is where it can get lost, because
 * \Drupal\Core\Config\StorableConfigBase::castValue() sanitizes every value
 * before it is written: for an element implementing IntegerInterface it turns
 * an empty string into NULL and hands everything else to getCastedValue().
 * A plain "integer" or "weight" element therefore casts "[my:weight]" to the
 * integer 0, and because 0 is a perfectly legal weight nothing ever surfaces -
 * the token is gone by the time the plugin looks for it.
 *
 * ECA avoids that with the "eca_integer_or_token" and "eca_float_or_token"
 * data types, which return the raw string instead of casting it. Those types
 * are never written into a schema file by hand, they are applied to every
 * numeric key of every ECA plugin by hook_config_schema_info_alter().
 *
 * Unlike the other schema tests in this directory, this one does not inspect
 * schema definitions. It asserts the behavior those definitions exist for, by
 * round-tripping a real ECA configuration entity through the configuration
 * storage. A key that the alter hook fails to reach passes every definition
 * level assertion and still silently drops the user's token here.
 *
 * @see \Drupal\eca\Hook\ConfigSchemaHooks::configSchemaInfoAlter()
 * @see \Drupal\eca\Hook\ConfigSchemaHooksTrait::alterSchemaFieldType()
 * @see \Drupal\eca\Plugin\DataType\IntegerOrToken
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class TokenizedNumericConfigRoundTripTest extends KernelTestBase {

  /**
   * The token used as a configuration value throughout this test.
   */
  private const TOKEN = '[my:weight]';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_form',
    'eca_queue',
    'eca_render',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['eca']);
  }

  /**
   * Tests tokenized weights on render and form actions.
   *
   * "weight" is declared once, on the shared
   * "eca_render.render_element_action_base" type that render element action
   * schema sections descend from. Type inheritance is resolved lazily, after
   * hook_config_schema_info_alter() has run against the raw per-file
   * definitions, so a hook that only visits the leaf "action.configuration.*"
   * keys never sees this key at all.
   * The render action inherits "weight" from a shared base type. The form
   * action declares the same token-capable concept directly. Both must resolve
   * to the integer-or-token type and preserve token values during storage.
   */
  public function testTokenizedWeightsOfActionsSurviveRoundTrip(): void {
    $eca = $this->saveAndReload();
    $actions = $eca->get('actions');
    $this->assertSame(self::TOKEN, $actions['Activity_markup']['configuration']['weight']);
    $this->assertSame(self::TOKEN, $actions['Activity_form_weight']['configuration']['weight']);
    $this->assertSame('eca_integer_or_token', $this->container->get('config.typed')
      ->getDefinition('action.configuration.eca_form_field_set_weight')['mapping']['weight']['type']);
  }

  /**
   * Tests tokenized numeric keys of event plugins.
   *
   * Event plugin configuration is stored under "eca.event.plugin.*", which
   * hook_config_schema_info_alter() did not visit at all: it iterated actions
   * and conditions only.
   */
  public function testTokenizedNumericEventConfigurationSurvivesRoundTrip(): void {
    $eca = $this->saveAndReload();
    $events = $eca->get('events');
    $this->assertSame(self::TOKEN, $events['Event_extra_field']['configuration']['weight']);
    $this->assertSame(self::TOKEN, $events['Event_queue']['configuration']['cron']);
  }

  /**
   * Tests that a token-shaped value is not the only string that survives.
   *
   * Select elements offering "Defined by token" store the "_eca_token"
   * sentinel, which is not wrapped in brackets. It has to survive the same way
   * a token does, otherwise it is silently turned into a weight of 0.
   */
  public function testSentinelValueSurvivesRoundTrip(): void {
    $eca = $this->saveAndReload(['Activity_markup' => '_eca_token']);
    $actions = $eca->get('actions');
    $this->assertSame('_eca_token', $actions['Activity_markup']['configuration']['weight']);
  }

  /**
   * Tests that real numbers are still stored as numbers.
   *
   * A no-regression control: the token support must not stop an actual integer
   * from being stored as an integer, because plenty of stored configuration
   * holds real numbers today and would start failing schema checking.
   */
  public function testNumericValuesAreStillStoredAsIntegers(): void {
    $eca = $this->saveAndReload([
      // A negative weight is the ordinary case for a weight, and the leading
      // minus sign must not make it look like a non-numeric string.
      'Activity_markup' => '-5',
      'Activity_form_weight' => '-6',
      'Event_extra_field' => 7,
      'Event_queue' => '7',
    ]);
    $this->assertSame(-5, $eca->get('actions')['Activity_markup']['configuration']['weight']);
    $this->assertSame(-6, $eca->get('actions')['Activity_form_weight']['configuration']['weight']);
    $this->assertSame(7, $eca->get('events')['Event_extra_field']['configuration']['weight']);
    $this->assertSame(7, $eca->get('events')['Event_queue']['configuration']['cron']);
  }

  /**
   * Tests that a non-integer numeric string is preserved, not truncated.
   *
   * A float such as "1.5" on an integer-or-token key must survive as the raw
   * string. is_numeric("1.5") is TRUE, so a cast would truncate it to the
   * integer 1 and silently corrupt the stored value.
   */
  public function testFloatValueIsPreservedAsString(): void {
    $eca = $this->saveAndReload(['Activity_markup' => '1.5']);
    $this->assertSame('1.5', $eca->get('actions')['Activity_markup']['configuration']['weight']);
  }

  /**
   * Tests that an unset numeric value is stored as NULL.
   *
   * This documents the reason the runtime guard cannot only look for an empty
   * string: castValue() turns the empty string default of a non-required
   * numeric element into NULL, so NULL is what "no value given" looks like once
   * the configuration has been through the storage.
   */
  public function testEmptyValueIsStoredAsNull(): void {
    $eca = $this->saveAndReload([
      'Activity_markup' => '',
      'Activity_form_weight' => '',
      'Event_extra_field' => '',
      'Event_queue' => '',
    ]);
    $this->assertNull($eca->get('actions')['Activity_markup']['configuration']['weight']);
    $this->assertNull($eca->get('actions')['Activity_form_weight']['configuration']['weight']);
    $this->assertNull($eca->get('events')['Event_extra_field']['configuration']['weight']);
    $this->assertNull($eca->get('events')['Event_queue']['configuration']['cron']);
  }

  /**
   * Tests that installing a module shipping an ECA model still works.
   *
   * The alter hook now instantiates every event plugin, on top of the actions
   * and conditions it already instantiated, and it does so while typed
   * configuration builds its definitions. Module installation is the awkward
   * moment for that: the container and the plugin caches are rebuilt and
   * configuration is written in the same request, so the hook runs against
   * caches that were just discarded.
   *
   * The module installed here ships an ECA model whose "eca_render:extra_field"
   * event carries a weight, which is one of the keys this change retypes, so
   * the install writes a value through the very schema the hook rewrote.
   *
   * Installing at runtime is the point of this test: the other tests covering
   * model shipping modules list them in "$modules" and let the base class call
   * installConfig(), which runs once the container is already warm.
   */
  public function testInstallingModuleWithShippedModelSucceeds(): void {
    $this->assertNull($this->config('eca.eca.eca_test_render_extra_field')->get('id'));

    $this->container->get('module_installer')->install(['eca_test_render_extra_field']);
    // The container is rebuilt by the installation.
    $this->container = \Drupal::getContainer();

    $config = $this->config('eca.eca.eca_test_render_extra_field');
    $this->assertSame('eca_test_render_extra_field', $config->get('id'));
    // The model ships the integer 0, which must survive as an integer.
    $this->assertSame(0, $config->get('events.Event_display.configuration.weight'));
    $this->assertSame('eca_integer_or_token', $this->container->get('config.typed')
      ->getDefinition('eca.event.plugin.eca_render:extra_field')['mapping']['weight']['type']);
  }

  /**
   * Saves an ECA entity with the given numeric values and reloads it.
   *
   * Reloading really does go back to the configuration storage, so the returned
   * entity carries the values as they were written, not as they were handed in.
   *
   * @param array $values
   *   Numeric configuration values keyed by the component ID they belong to.
   *   Defaults to the token for every component.
   *
   * @return \Drupal\eca\Entity\Eca
   *   The reloaded ECA entity.
   */
  private function saveAndReload(array $values = []): Eca {
    $values += [
      'Activity_markup' => self::TOKEN,
      'Activity_form_weight' => self::TOKEN,
      'Event_extra_field' => self::TOKEN,
      'Event_queue' => self::TOKEN,
    ];
    $storage = $this->container->get('entity_type.manager')->getStorage('eca');
    $storage->create([
      'id' => 'tokenized_numeric_round_trip',
      'status' => TRUE,
      'events' => [
        'Event_extra_field' => [
          'plugin' => 'eca_render:extra_field',
          'label' => 'Extra field',
          'configuration' => [
            'entity_type_id' => '',
            'bundle' => '',
            'extra_field_name' => 'eca_test_extra_field',
            'extra_field_label' => 'ECA test extra field',
            'extra_field_description' => '',
            'display_type' => 'display',
            'weight' => $values['Event_extra_field'],
            'visible' => TRUE,
          ],
          'successors' => [
            ['id' => 'Activity_markup', 'condition' => ''],
          ],
        ],
        'Event_queue' => [
          'plugin' => 'eca_queue:processing_task',
          'label' => 'Queue processing task',
          'configuration' => [
            'task_name' => 'eca_test_task',
            'task_value' => '',
            'distribute' => FALSE,
            'cron' => $values['Event_queue'],
          ],
          'successors' => [
            ['id' => 'Activity_markup', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'Activity_markup' => [
          'plugin' => 'eca_render_markup',
          'label' => 'Render markup',
          'configuration' => [
            'name' => '',
            'token_name' => '',
            'weight' => $values['Activity_markup'],
            'mode' => 'append',
            'value' => 'Some markup',
            'use_yaml' => FALSE,
            'validate_yaml' => FALSE,
          ],
          'successors' => [],
        ],
        'Activity_form_weight' => [
          'plugin' => 'eca_form_field_set_weight',
          'label' => 'Set form field weight',
          'configuration' => [
            'field_name' => 'example',
            'weight' => $values['Activity_form_weight'],
          ],
          'successors' => [],
        ],
      ],
    ])->save();

    $storage->resetCache(['tokenized_numeric_round_trip']);
    $eca = $storage->load('tokenized_numeric_round_trip');
    $this->assertInstanceOf(Eca::class, $eca);
    return $eca;
  }

}
