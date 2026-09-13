<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\Validation\Constraint\EcaIntegerOrTokenConstraint;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ECA configuration schema definitions.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ConfigSchemaTest extends KernelTestBase {

  /**
   * A configuration schema type declaring a "weight" typed key.
   *
   * Core declares its "weight" type with a "Range" constraint, so this is the
   * kind of key that has value bounds to lose when it gets retyped.
   */
  private const string WEIGHT_SCHEMA_TYPE = 'action.configuration.eca_form_field_set_weight';

  /**
   * A configuration schema type declaring a plain "integer" typed key.
   *
   * Core's "integer" type carries no constraints, and this particular key
   * declares none of its own either, so the integer-or-token constraint is the
   * only thing that can reject a value here.
   */
  private const string INTEGER_SCHEMA_TYPE = 'eca.event.plugin.eca_queue:processing_task';

  /**
   * Values that every integer-or-token configuration key has to accept.
   *
   * The list mirrors what IntegerOrToken::getCastedValue() passes through
   * unchanged, because exactly those values reach configuration storage the way
   * they were entered and therefore have to pass validation too. A single
   * token is only the most obvious of them: numeric keys also hold the
   * "_eca_token" sentinel of a select element offering "Defined by token", and
   * token expressions built from more than one token.
   *
   * @see \Drupal\eca\Plugin\DataType\IntegerOrToken::getCastedValue()
   */
  private const array ACCEPTED_VALUES = [
    6,
    '6',
    '-6',
    '_eca_token',
    '[integer_value]',
    // Two tokens with nothing between them, and two with literal text between
    // them. Neither is a single bracketed token, and both survive storage.
    '[a][b]',
    '[node:field_a]-[node:field_b]',
  ];

  /**
   * Values that no integer-or-token configuration key may accept.
   *
   * The empty string never reaches storage as itself: castValue() turns it into
   * NULL, which is how "no value given" is stored. A fractional number is not
   * a value an integer key can hold at all, and casting it would silently
   * truncate it.
   *
   * @see \Drupal\Core\Config\StorableConfigBase::castValue()
   */
  private const array REJECTED_VALUES = ['', 1.5];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
    'eca_form',
    'eca_log',
    'eca_misc',
    'eca_queue',
    'modeler_api',
  ];

  /**
   * Tests the event plugin schema and event plugin validation.
   */
  public function testEventPluginSchema(): void {
    $typed_config_manager = $this->container->get('config.typed');
    $event_plugin_definition = $typed_config_manager->getDefinition('eca.event.plugin');
    $event_definition = $typed_config_manager->getDefinition('eca.eca.*');

    $this->assertArrayNotHasKey('id', $event_plugin_definition['mapping']);
    $this->assertSame([
      'manager' => 'plugin.manager.eca.event',
      'interface' => 'Drupal\\eca\\Plugin\\ECA\\Event\\EventInterface',
    ], $event_definition['mapping']['events']['sequence']['mapping']['plugin']['constraints']['PluginExists']);
  }

  /**
   * Tests integer configuration values that may be defined by a token.
   */
  public function testIntegerOrTokenSchema(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $dataDefinition = $typedConfigManager->createDataDefinition('eca_integer_or_token');
    foreach (self::ACCEPTED_VALUES as $value) {
      $violations = $typedConfigManager->create($dataDefinition, $value)->validate();
      $this->assertCount(0, $violations, sprintf('The type accepts %s.', var_export($value, TRUE)));
    }
    foreach (self::REJECTED_VALUES as $value) {
      $violations = $typedConfigManager->create($dataDefinition, $value)->validate();
      $this->assertCount(1, $violations, sprintf('The type rejects %s.', var_export($value, TRUE)));
    }

    $keys = [
      ['eca.condition.plugin.eca_route_match', 'request', 1],
      ['action.configuration.eca_token_load_route_param', 'request', 1],
      ['action.configuration.eca_write_log_message', 'severity', 6],
      ['action.configuration.eca_enqueue_task_delayed', 'delay_unit', 60],
    ];

    foreach ($keys as [$schemaType, $key, $integerValue]) {
      $definition = $typedConfigManager->getDefinition($schemaType);
      $this->assertSame('eca_integer_or_token', $definition['mapping'][$key]['type']);
      foreach ([$integerValue, '_eca_token'] as $value) {
        $data = [$key => $value];
        $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
        $typedData = $typedConfigManager->create($dataDefinition, $data);
        $this->assertInstanceOf(IntegerInterface::class, $typedData->get($key));
        $this->assertInstanceOf(StringInterface::class, $typedData->get($key));
        $violations = $typedData->validate();
        $this->assertCount(0, $violations, sprintf('%s:%s accepts %s.', $schemaType, $key, var_export($value, TRUE)));
      }
    }
  }

  /**
   * Tests that a retyped "weight" key keeps the value bounds core declares.
   *
   * Every "weight" key of an ECA plugin is retyped to "eca_integer_or_token" by
   * hook_config_schema_info_alter(). That takes the key out of core's "weight"
   * type and with it away from the "Range" constraint that type carries, so an
   * out-of-range weight would no longer be reported anywhere. The bounds are
   * carried over to the replacement type to keep that from happening.
   *
   * The "Range" constraint itself cannot be carried over: its validator reports
   * every non-numeric value as an invalid number, which would turn each of the
   * token values this type exists for into a validation error.
   *
   * @see \Drupal\eca\Hook\ConfigSchemaHooksTrait::alterSchemaFieldType()
   * @see \Symfony\Component\Validator\Constraints\RangeValidator::validate()
   */
  public function testWeightBoundsSurviveRetyping(): void {
    $typedConfigManager = $this->container->get('config.typed');
    // The bounds are read back from core rather than repeated here, so that
    // this asserts they were inherited, not that they happen to match a pair of
    // numbers this test knows.
    $range = $typedConfigManager->getDefinition('weight')['constraints']['Range'];

    // The behavior comes first, so that a regression is reported as the value
    // that stopped being handled rather than as a missing definition key.
    // A weight beyond either bound is reported, naming both of them.
    $expected = sprintf('This value should be between %d and %d.', $range['min'], $range['max']);
    foreach ([$range['min'] - 1, $range['max'] + 1] as $value) {
      $this->assertSame([$expected], $this->violationMessages(self::WEIGHT_SCHEMA_TYPE, 'weight', $value), sprintf('The weight key rejects %s.', var_export($value, TRUE)));
    }
    // Both bounds are inclusive, and none of the token values is affected by
    // them at all.
    foreach ([...self::ACCEPTED_VALUES, $range['min'], $range['max']] as $value) {
      $this->assertSame([], $this->violationMessages(self::WEIGHT_SCHEMA_TYPE, 'weight', $value), sprintf('The weight key accepts %s.', var_export($value, TRUE)));
    }

    $definition = $typedConfigManager->getDefinition(self::WEIGHT_SCHEMA_TYPE);
    $this->assertSame('eca_integer_or_token', $definition['mapping']['weight']['type']);
    $this->assertSame([
      'min' => $range['min'],
      'max' => $range['max'],
    ], $definition['mapping']['weight']['constraints']['EcaIntegerOrToken']);

    // The bounds also have to survive being turned into a constraint object,
    // which only happens because the constraint constructor is marked with the
    // "HasNamedArguments" attribute: that is what makes ConstraintFactory
    // spread an associative option array as named arguments instead of handing
    // the whole array to the first parameter.
    // @see \Drupal\Core\Validation\ConstraintFactory::createInstance()
    $constraint = $this->getIntegerOrTokenConstraint(self::WEIGHT_SCHEMA_TYPE, 'weight');
    $this->assertSame($range['min'], $constraint->min);
    $this->assertSame($range['max'], $constraint->max);
  }

  /**
   * Tests a bound that is declared on one side only.
   *
   * Core's "weight" bounds its value on both sides, so nothing in ECA currently
   * inherits a single bound. The two are independent options all the same, and
   * a type declaring only one of them has to be reported against that one
   * rather than be silently unbounded.
   *
   * Adding the constraint to the data definition by hand is what makes a single
   * bound reachable at all, and it exercises the same ConstraintFactory path
   * that the schema definitions go through.
   */
  public function testSingleBoundIsApplied(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $bounds = [
      [['min' => 10], 9, 'This value should be 10 or more.'],
      [['max' => 10], 11, 'This value should be 10 or less.'],
    ];
    foreach ($bounds as [$options, $rejected, $message]) {
      $dataDefinition = $typedConfigManager->createDataDefinition('eca_integer_or_token');
      $dataDefinition->addConstraint('EcaIntegerOrToken', $options);
      $violations = $typedConfigManager->create($dataDefinition, $rejected)->validate();
      $this->assertCount(1, $violations, sprintf('%s rejects %d.', key($options), $rejected));
      $this->assertSame($message, strip_tags((string) $violations->get(0)->getMessage()));
      // The bound applies to integers only, so every value that is not one
      // stays valid however far it is from the bound, and the value on the
      // bound itself is accepted.
      $notIntegers = array_filter(self::ACCEPTED_VALUES, static fn ($value) => filter_var($value, FILTER_VALIDATE_INT) === FALSE);
      foreach ([...$notIntegers, 10] as $value) {
        $violations = $typedConfigManager->create($dataDefinition, $value)->validate();
        $this->assertCount(0, $violations, sprintf('%s accepts %s.', key($options), var_export($value, TRUE)));
      }
    }
  }

  /**
   * Tests that a plain "integer" key gains no bounds it never had.
   *
   * Core's "integer" type declares no constraints, so there is nothing for the
   * retyped key to inherit and no value it should start rejecting.
   */
  public function testIntegerKeyHasNoBounds(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $definition = $typedConfigManager->getDefinition(self::INTEGER_SCHEMA_TYPE);
    $this->assertSame('eca_integer_or_token', $definition['mapping']['cron']['type']);
    $this->assertArrayNotHasKey('constraints', $definition['mapping']['cron']);

    // With no bounds to inherit, the constraint is built from an empty option
    // array, which ConstraintFactory has to route to a constructor call that
    // takes no arguments at all rather than to the named-argument one.
    // @see \Drupal\Core\Validation\ConstraintFactory::createInstance()
    $constraint = $this->getIntegerOrTokenConstraint(self::INTEGER_SCHEMA_TYPE, 'cron');
    $this->assertNull($constraint->min);
    $this->assertNull($constraint->max);

    $range = $typedConfigManager->getDefinition('weight')['constraints']['Range'];
    foreach ([...self::ACCEPTED_VALUES, $range['min'] - 1, $range['max'] + 1] as $value) {
      $this->assertSame([], $this->violationMessages(self::INTEGER_SCHEMA_TYPE, 'cron', $value), sprintf('The integer key accepts %s.', var_export($value, TRUE)));
    }
  }

  /**
   * Gets the integer-or-token constraint object built for a configuration key.
   *
   * @param string $schemaType
   *   The configuration schema type declaring the key.
   * @param string $key
   *   The configuration key to get the constraint of.
   *
   * @return \Drupal\eca\Plugin\Validation\Constraint\EcaIntegerOrTokenConstraint
   *   The constraint object, as the validator receives it.
   */
  private function getIntegerOrTokenConstraint(string $schemaType, string $key): EcaIntegerOrTokenConstraint {
    $typedConfigManager = $this->container->get('config.typed');
    $data = [$key => 0];
    $definition = $typedConfigManager->getDefinition($schemaType);
    $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
    $constraints = $typedConfigManager->create($dataDefinition, $data)->get($key)->getConstraints();
    foreach ($constraints as $constraint) {
      if ($constraint instanceof EcaIntegerOrTokenConstraint) {
        return $constraint;
      }
    }
    $this->fail(sprintf('%s:%s carries an "EcaIntegerOrToken" constraint.', $schemaType, $key));
  }

  /**
   * Validates a single configuration key and collects the violation messages.
   *
   * @param string $schemaType
   *   The configuration schema type declaring the key.
   * @param string $key
   *   The configuration key to validate.
   * @param mixed $value
   *   The value to validate the key with.
   *
   * @return string[]
   *   The violation messages, so that an unexpected one is readable in the
   *   failure output instead of only being counted. The markup Drupal wraps a
   *   message placeholder in is stripped: it is how the message is rendered,
   *   not part of what these tests are about.
   */
  private function violationMessages(string $schemaType, string $key, mixed $value): array {
    $typedConfigManager = $this->container->get('config.typed');
    $data = [$key => $value];
    $definition = $typedConfigManager->getDefinition($schemaType);
    $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
    $messages = [];
    foreach ($typedConfigManager->create($dataDefinition, $data)->validate() as $violation) {
      $messages[] = strip_tags((string) $violation->getMessage());
    }
    return $messages;
  }

}
