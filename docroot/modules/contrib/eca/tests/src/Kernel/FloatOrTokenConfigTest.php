<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests float configuration values that may be defined by a token.
 *
 * The float counterpart of the integer coverage, which is split across
 * ConfigSchemaTest (validation) and TokenizedNumericConfigRoundTripTest
 * (storage). Both levels are kept together here because reaching them for a
 * float needs a fixture the integer tests do not: no ECA plugin ships a
 * "float" typed configuration key, so the "float" arm of
 * ConfigSchemaHooksTrait::alterSchemaFieldType() is unreachable from real
 * plugin schema and the "eca_test_float_config" module declares one.
 *
 * The bug this covers is that "eca_float_or_token" had no definition_class,
 * so unlike "eca_integer_or_token" it never unset core's "PrimitiveType"
 * constraint. PrimitiveTypeConstraintValidator rejects anything that is not a
 * float, so a token was rejected by validation while
 * FloatOrToken::getCastedValue() passed it straight through to storage.
 *
 * @see \Drupal\eca\Plugin\DataType\FloatOrToken::getCastedValue()
 * @see \Drupal\eca\TypedData\FloatOrTokenDataDefinition
 * @see \Drupal\Tests\eca\Kernel\ConfigSchemaTest::testIntegerOrTokenSchema()
 * @see \Drupal\Tests\eca\Kernel\TokenizedNumericConfigRoundTripTest
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class FloatOrTokenConfigTest extends KernelTestBase {

  /**
   * The configuration schema type declaring the float typed key.
   */
  private const string FLOAT_SCHEMA_TYPE = 'action.configuration.eca_test_float_config_set';

  /**
   * Values that every float-or-token configuration key has to accept.
   *
   * The list mirrors what FloatOrToken::getCastedValue() either casts or passes
   * through unchanged, because every one of them reaches configuration storage
   * and therefore has to pass validation too. A single token is only the most
   * obvious of them: numeric keys also hold the "_eca_token" sentinel of a
   * select element offering "Defined by token", and token expressions built
   * from more than one token.
   *
   * Unlike the integer list, a fractional value belongs here rather than among
   * the rejected ones: it is exactly what a float key is for.
   *
   * @see \Drupal\eca\Plugin\DataType\FloatOrToken::getCastedValue()
   */
  private const array ACCEPTED_VALUES = [
    1.5,
    '1.5',
    '-1.5',
    // An integer shaped value is a perfectly good float.
    6,
    '6',
    '-6',
    // Scientific notation is numeric to both PHP and this type.
    '1e5',
    '_eca_token',
    '[float_value]',
    // Two tokens with nothing between them, and two with literal text between
    // them. Neither is a single bracketed token, and both survive storage.
    '[a][b]',
    '[node:field_a]-[node:field_b]',
  ];

  /**
   * Values that no float-or-token configuration key may accept.
   *
   * The empty string never reaches storage as itself: castValue() turns it into
   * NULL, which is how "no value given" is stored. There is no numeric
   * counterpart to the integer type's rejected "1.5" here, because no numeric
   * value is out of place on a float key.
   *
   * @see \Drupal\Core\Config\StorableConfigBase::castValue()
   */
  private const array REJECTED_VALUES = [''];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_test_float_config',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['eca']);
  }

  /**
   * Tests that the data type accepts floats and tokens alike.
   */
  public function testFloatOrTokenDataType(): void {
    $typedConfigManager = $this->container->get('config.typed');
    foreach (self::ACCEPTED_VALUES as $value) {
      $dataDefinition = $typedConfigManager->createDataDefinition('eca_float_or_token');
      $violations = $typedConfigManager->create($dataDefinition, $value)->validate();
      $this->assertCount(0, $violations, sprintf('The type accepts %s.', var_export($value, TRUE)));
    }
    foreach (self::REJECTED_VALUES as $value) {
      $dataDefinition = $typedConfigManager->createDataDefinition('eca_float_or_token');
      $violations = $typedConfigManager->create($dataDefinition, $value)->validate();
      $this->assertCount(1, $violations, sprintf('The type rejects %s.', var_export($value, TRUE)));
    }
  }

  /**
   * Tests that NULL is accepted, which is how an unset value is stored.
   */
  public function testNullIsAccepted(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $dataDefinition = $typedConfigManager->createDataDefinition('eca_float_or_token');
    $violations = $typedConfigManager->create($dataDefinition, NULL)->validate();
    $this->assertCount(0, $violations, 'The type accepts NULL.');
  }

  /**
   * Tests that the float typed key is retyped and validates both shapes.
   *
   * This is the half that needs the fixture module: it goes through the schema
   * definition of a real plugin configuration section, which is what the alter
   * hook rewrites.
   */
  public function testFloatKeyIsRetyped(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $definition = $typedConfigManager->getDefinition(self::FLOAT_SCHEMA_TYPE);
    $this->assertSame('eca_float_or_token', $definition['mapping']['ratio']['type']);

    foreach ([1.5, '_eca_token', '[float_value]'] as $value) {
      $data = ['ratio' => $value];
      $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
      $typedData = $typedConfigManager->create($dataDefinition, $data);
      $this->assertInstanceOf(FloatInterface::class, $typedData->get('ratio'));
      $this->assertInstanceOf(StringInterface::class, $typedData->get('ratio'));
      $violations = $typedData->validate();
      $this->assertCount(0, $violations, sprintf('%s:ratio accepts %s.', self::FLOAT_SCHEMA_TYPE, var_export($value, TRUE)));
    }
  }

  /**
   * Tests that the retyped key carries no value bounds.
   *
   * Core's "float" type declares no constraints, so unlike the integer side
   * there is nothing to carry over and the constraint takes no bounds.
   *
   * @see \Drupal\eca\Plugin\Validation\Constraint\EcaFloatOrTokenConstraint
   */
  public function testFloatKeyHasNoBounds(): void {
    $definition = $this->container->get('config.typed')->getDefinition(self::FLOAT_SCHEMA_TYPE);
    $this->assertArrayNotHasKey('constraints', $definition['mapping']['ratio']);
  }

  /**
   * Tests that tokens on a float key survive being written to storage.
   *
   * The round trip is what the validation exists to keep working: a value that
   * validation rejects never reaches storage at all under strict schema
   * checking.
   */
  public function testTokenValuesSurviveRoundTrip(): void {
    foreach (['[float_value]', '_eca_token', '[a][b]', '[node:field_a]-[node:field_b]'] as $value) {
      $eca = $this->saveAndReload($value);
      $this->assertSame($value, $eca->get('actions')['Activity_float']['configuration']['ratio']);
    }
  }

  /**
   * Tests that real numbers are still stored as floats.
   *
   * A no-regression control: the token support must not stop an actual number
   * from being stored as a float.
   */
  public function testNumericValuesAreStillStoredAsFloats(): void {
    foreach (['1.5' => 1.5, '-1.5' => -1.5, '6' => 6.0, '1e5' => 100000.0] as $given => $expected) {
      $eca = $this->saveAndReload((string) $given);
      $this->assertSame($expected, $eca->get('actions')['Activity_float']['configuration']['ratio']);
    }
  }

  /**
   * Tests that an unset float value is stored as NULL.
   *
   * The storage turns the empty string default of a non-required numeric
   * element into NULL in castValue(), which is why the empty string is not
   * among the values validation accepts.
   */
  public function testEmptyValueIsStoredAsNull(): void {
    $eca = $this->saveAndReload('');
    $this->assertNull($eca->get('actions')['Activity_float']['configuration']['ratio']);
  }

  /**
   * Saves an ECA entity carrying the given float value and reloads it.
   *
   * Reloading really does go back to the configuration storage, so the returned
   * entity carries the value as it was written, not as it was handed in.
   *
   * @param mixed $ratio
   *   The value for the float typed configuration key.
   *
   * @return \Drupal\eca\Entity\Eca
   *   The reloaded ECA entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function saveAndReload(mixed $ratio): Eca {
    $storage = $this->container->get('entity_type.manager')->getStorage('eca');
    $storage->delete($storage->loadMultiple(['float_or_token_round_trip']));
    $storage->create([
      'id' => 'float_or_token_round_trip',
      'status' => TRUE,
      'events' => [],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'Activity_float' => [
          'plugin' => 'eca_test_float_config_set',
          'label' => 'Set float',
          'configuration' => [
            'ratio' => $ratio,
          ],
          'successors' => [],
        ],
      ],
    ])->save();

    $storage->resetCache(['float_or_token_round_trip']);
    $eca = $storage->load('float_or_token_round_trip');
    $this->assertInstanceOf(Eca::class, $eca);
    return $eca;
  }

}
