<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for data transfer objects.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class DataTransferObjectTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_test_array',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    // Create an Article content type.
    $this->createContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Tests collecting data from multiple sources and saving them at once.
   */
  public function testDtoSave(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
      'uid' => 0,
    ]);
    $dto = DataTransferObject::create();
    $dto->set('title', $node->get('title'));
    $user = User::load(1);
    $dto->set('username', $user->get('name'));
    $node_type = NodeType::load('article');
    $dto->set('node_type', $node_type);

    $new_title = $this->randomMachineName();
    $dto->get('title')->setValue($new_title);
    $this->assertEquals($new_title, $node->title->value);

    $new_username = $this->randomMachineName();
    $dto->get('username')->setValue($new_username);
    $this->assertEquals($new_username, $user->name->value);

    $dto->get('node_type')->getValue()->set('name', 'ECA Article');
    $this->assertEquals('ECA Article', $node_type->get('name'));

    $this->assertTrue($node->isNew());
    $dto->saveData();
    $this->assertFalse($node->isNew());
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals($new_title, $node->title->value);
    $user = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged(1);
    $this->assertEquals($new_username, $user->name->value);
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->loadUnchanged('article');
    $this->assertEquals('ECA Article', $node_type->get('name'));
  }

  /**
   * Tests removing values.
   */
  public function testRemove(): void {
    $user1 = User::create(['name' => 'user1']);
    $user1->save();
    $user2 = User::create(['name' => 'user2']);
    $user2->save();
    $user3 = User::create(['name' => 'user3']);
    $user3->save();
    $user4 = User::create(['name' => 'user4']);
    $user4->save();
    $dto = DataTransferObject::create([$user1, $user2, $user3]);
    $this->assertSame(3, $dto->count());
    $item = $dto->remove($user4);
    $this->assertNull($item);
    $this->assertSame(3, $dto->count());
    $item = $dto->remove($user2);
    $this->assertNotNull($item);
    $this->assertSame(2, $dto->count());
    $cloned_user1 = clone $user1;
    $item = $dto->remove($cloned_user1);
    $this->assertNotNull($item);
    $this->assertSame(1, $dto->count());
  }

  /**
   * Tests the URL data type's string representations.
   */
  public function testEcaUrlStringRepresentations(): void {
    $manager = \Drupal::typedDataManager();
    $definition = $manager->createDataDefinition('eca_url');
    /** @var \Drupal\eca\Plugin\DataType\EcaUrl $unset */
    $unset = $manager->createInstance('eca_url', [
      'data_definition' => $definition,
      'name' => 'unset',
      'parent' => NULL,
    ]);
    $this->assertSame('', $unset->getString());

    /** @var \Drupal\eca\Plugin\DataType\EcaUrl $valid */
    $valid = $manager->createInstance('eca_url', [
      'data_definition' => $definition,
      'name' => 'valid',
      'parent' => NULL,
    ]);
    $valid->setValue(Url::fromRoute('<front>'));
    $this->assertSame('/', $valid->getString());

    /** @var \Drupal\eca\Plugin\DataType\EcaUrl $unknown */
    $unknown = $manager->createInstance('eca_url', [
      'data_definition' => $definition,
      'name' => 'unknown',
      'parent' => NULL,
    ]);
    $unknown->setValue(Url::fromRoute('this.route.does.not.exist'));
    $this->assertSame('[object Drupal\\Core\\Url]', $unknown->getString());

    $valid_dto = DataTransferObject::create(['url' => Url::fromRoute('<front>')]);
    $this->assertSame('/', $valid_dto->get('url')->getString());
    $this->assertSame(Yaml::encode(['url' => '/']), (string) $valid_dto);

    $dto = DataTransferObject::create(['url' => Url::fromRoute('this.route.does.not.exist')]);
    $this->assertSame('[object Drupal\\Core\\Url]', $dto->get('url')->getString());
    $this->assertSame(Yaml::encode(['url' => '[object Drupal\\Core\\Url]']), (string) $dto);
  }

  /**
   * Tests that entities nested in a property value are converted to arrays.
   */
  public function testGetStringConvertsNestedEntities(): void {
    $user1 = User::create(['name' => 'user1']);
    $user1->save();
    $user2 = User::create(['name' => 'user2']);
    $user2->save();

    // The 'types' and 'values' form of ::setValue() is required here. It is
    // the only construction that lets entity objects survive as far as
    // ::getString(). Entities passed in any other way get wrapped by an
    // EntityAdapter, which implements ComplexDataInterface, so ::getString()
    // takes the ::toArray() path and receives field values instead of the
    // entity objects themselves. The 'any' data type is not complex data, so
    // ::getValue() hands back the raw array with the entities still in it.
    $dto = DataTransferObject::create([
      'types' => ['x' => 'any'],
      'values' => ['x' => [$user1, $user2]],
    ]);

    // Without the conversion, Yaml::encode() would refuse to dump the objects
    // and throw an InvalidDataTypeException instead.
    $expected = Yaml::encode(['x' => [$user1->toArray(), $user2->toArray()]]);
    $this->assertSame($expected, $dto->getString());

    // Assert the shape as well, so a failure shows whether the entities were
    // expanded into their field values rather than merely not throwing.
    $decoded = Yaml::decode($dto->getString());
    $this->assertCount(2, $decoded['x']);
    $this->assertSame('user1', $decoded['x'][0]['name'][0]['value']);
    $this->assertSame('user2', $decoded['x'][1]['name'][0]['value']);
    $this->assertSame($user1->id(), (string) $decoded['x'][0]['uid'][0]['value']);
    $this->assertSame($user2->id(), (string) $decoded['x'][1]['uid'][0]['value']);
  }

  /**
   * Tests that only genuinely empty property values are skipped.
   */
  public function testGetStringSkipsEmptyValues(): void {
    // Using the 'types' and 'values' form keeps the raw PHP values intact.
    // Passing them as plain values would route them through the scalar and
    // iterable wrappers, which coerce types and would defeat the purpose.
    $dto = DataTransferObject::create([
      'types' => [
        'empty_array' => 'any',
        'empty_string' => 'any',
        'null_value' => 'any',
        'non_empty_array' => 'any',
        'zero_string' => 'any',
        'zero_int' => 'any',
      ],
      'values' => [
        'empty_array' => [],
        'empty_string' => '',
        'null_value' => NULL,
        'non_empty_array' => ['first', 'second'],
        'zero_string' => '0',
        'zero_int' => 0,
      ],
    ]);

    $string = $dto->getString();
    $expected = Yaml::encode([
      'non_empty_array' => ['first', 'second'],
      'zero_string' => '0',
      'zero_int' => 0,
    ]);
    $this->assertSame($expected, $string);

    // An empty array, an empty string and NULL are all dropped entirely.
    $decoded = Yaml::decode($string);
    $this->assertArrayNotHasKey('empty_array', $decoded);
    $this->assertArrayNotHasKey('empty_string', $decoded);
    $this->assertArrayNotHasKey('null_value', $decoded);
    $this->assertSame([
      'non_empty_array',
      'zero_string',
      'zero_int',
    ], array_keys($decoded));

    // Falsy but non-empty values must be kept, and must keep their type.
    $this->assertSame(['first', 'second'], $decoded['non_empty_array']);
    $this->assertSame('0', $decoded['zero_string']);
    $this->assertSame(0, $decoded['zero_int']);
  }

  /**
   * Tests that entity conversion reaches entities below the top level.
   *
   * Conversion used to walk only the top level of the property value, so an
   * entity one level deeper was handed to Yaml::encode() as an object and
   * threw an InvalidDataTypeException. Entities are now converted at any
   * nesting level, and a nested entity expands exactly as it already did one
   * level up. The boundary was depth rather than list versus assoc, so the
   * one level assoc case is asserted first below as a no-regression control.
   */
  public function testGetStringConvertsDeeplyNestedEntities(): void {
    $user = User::create(['name' => 'nested_user']);
    $user->save();

    // The 'types' and 'values' form of ::setValue() is required here, for the
    // same reason as in ::testGetStringConvertsNestedEntities(): it is the
    // only construction that lets entity objects survive as far as
    // ::getString(). Entities passed any other way are wrapped in an
    // EntityAdapter, which is complex data, so ::getString() takes the
    // ::toArray() path and never sees an entity object at all.
    $one_level = DataTransferObject::create([
      'types' => ['x' => 'any'],
      'values' => ['x' => ['member' => $user]],
    ]);

    // Control: an entity as a top level assoc value is converted correctly.
    $expected_one_level = Yaml::encode(['x' => ['member' => $user->toArray()]]);
    $this->assertSame($expected_one_level, $one_level->getString());

    // One level deeper, the very same entity is never reached.
    $two_levels = DataTransferObject::create([
      'types' => ['x' => 'any'],
      'values' => ['x' => ['group' => [$user]]],
    ]);
    $expected_two_levels = Yaml::encode([
      'x' => ['group' => [$user->toArray()]],
    ]);
    $this->assertSame($expected_two_levels, $two_levels->getString());
  }

  /**
   * Tests how non-entity objects in a property value are rendered.
   *
   * Objects other than entities used to reach Yaml::encode() and throw an
   * InvalidDataTypeException. Stringable objects are now cast to their string
   * form, which is consistent with ::writePropertyValue() already accepting
   * them as property values. MarkupInterface extends \Stringable, so render
   * markup is covered by that same rule.
   *
   * A Url has neither, but is rendered by its own branch, mirroring how
   * ::writePropertyValue() wraps a Url into the 'eca_url' data type. An object
   * that is none of these has no representation to fall back to and is
   * replaced by a marker naming its class, which keeps the value visible
   * instead of dropping it silently.
   */
  public function testGetStringHandlesNonEntityObjects(): void {
    // See ::testGetStringConvertsNestedEntities() for why the 'types' and
    // 'values' form is required to get a raw object as far as ::getString().
    $dto = DataTransferObject::create([
      'types' => ['x' => 'any'],
      'values' => [
        'x' => [
          'markup' => Markup::create('<em>markup</em>'),
          'url' => Url::fromRoute('<front>'),
          'plain_object' => new \stdClass(),
        ],
      ],
    ]);

    try {
      $string = $dto->getString();
    }
    catch (InvalidDataTypeException $e) {
      $this->fail(sprintf(
        '::getString() must not throw %s for a value the DTO accepted, but it threw: %s',
        InvalidDataTypeException::class,
        $e->getMessage()
      ));
    }

    $expected = Yaml::encode([
      'x' => [
        'markup' => '<em>markup</em>',
        'url' => '/',
        'plain_object' => '[object stdClass]',
      ],
    ]);
    $this->assertSame($expected, $string);
  }

  /**
   * Tests the fallback for a URL that cannot be rendered.
   *
   * Url::toString() generates the URL on demand and throws when the route
   * cannot be found, which happens for a model that refers to a route from a
   * module that is no longer installed. That must not escape ::getString(),
   * so such a URL falls back to the same marker as any other object that has
   * no string representation.
   */
  public function testGetStringFallsBackWhenUrlCannotBeRendered(): void {
    $dto = DataTransferObject::create([
      'types' => ['x' => 'any'],
      'values' => ['x' => ['url' => Url::fromRoute('this.route.does.not.exist')]],
    ]);

    try {
      $string = $dto->getString();
    }
    catch (\Throwable $e) {
      $this->fail(sprintf(
        '::getString() must not throw for a value the DTO accepted, but it threw %s: %s',
        $e::class,
        $e->getMessage()
      ));
    }

    $expected = Yaml::encode(['x' => ['url' => '[object Drupal\Core\Url]']]);
    $this->assertSame($expected, $string);
  }

  /**
   * Tests that an array containing itself is truncated instead of fatal.
   *
   * A property may hold an arbitrary array, including one that contains
   * itself. Converting the contained values without a nesting limit would
   * recurse until memory is exhausted, and so would Yaml::encode(). The limit
   * replaces the value at the boundary with a marker, which keeps the result
   * finite, encodable and visible.
   */
  public function testGetStringGuardsAgainstSelfReferentialArrays(): void {
    $cyclic = ['name' => 'cycle'];
    $cyclic['self'] = &$cyclic;

    $dto = DataTransferObject::create([
      'types' => ['x' => 'any'],
      'values' => ['x' => $cyclic],
    ]);

    $string = $dto->getString();

    // The traversal stops, so the result is finite and decodes as valid Yaml.
    $this->assertStringContainsString('maximum nesting level', $string);
    $decoded = Yaml::decode($string);
    $this->assertSame('cycle', $decoded['x']['name']);
    $this->assertSame('cycle', $decoded['x']['self']['name']);

    // Preparing the value must leave the value held by this object alone. A
    // referenced element such as the one above can otherwise be written
    // through, which a second identical call would expose.
    $this->assertSame($string, $dto->getString());
    $this->assertSame('cycle', $cyclic['self']['name']);
  }

  /**
   * Tests that equivalent construction paths accept the same values.
   */
  public function testEquivalentConstructionPaths(): void {
    $entity = Node::create(['type' => 'article', 'title' => 'Entity']);
    $config = \Drupal::configFactory()->getEditable('eca.test');
    $config->setData(['value' => 'config value']);
    $values = [
      'scalar' => 'value',
      'iterable' => ['nested' => 'value'],
      'markup' => Markup::create('<em>value</em>'),
      'url' => Url::fromUri('internal:/'),
      'stringable' => new class {

        /**
         * Returns the string representation.
         */
        public function __toString(): string {
          return 'value';
        }

      },
      'config' => $config,
      'entity' => $entity,
    ];
    $values['typed_data'] = $entity->get('title');

    $from_set_value = DataTransferObject::create($values);
    $from_set = DataTransferObject::create();
    foreach ($values as $name => $value) {
      $from_set->set($name, $value);
    }

    $this->assertSame($from_set_value->toArray(), $from_set->toArray());
  }

  /**
   * Tests that NULL values retain their removal behavior.
   */
  public function testNullRemovesValues(): void {
    $from_set_value = DataTransferObject::create(['value' => 'value']);
    $from_set_value->setValue(['value' => NULL]);
    $this->assertArrayNotHasKey('value', $from_set_value->getProperties());

    $from_set = DataTransferObject::create();
    $from_set->set('value', 'value');
    $from_set->set('value', NULL);
    $this->assertArrayNotHasKey('value', $from_set->getProperties());
  }

  /**
   * Tests that invalid values have the same error through both entry points.
   */
  public function testInvalidValueException(): void {
    $message = 'Invalid value given. Value must be of a scalar type, an entity, a config object, an iterable, stringable or a typed data object.';
    $invalid = new \stdClass();

    try {
      DataTransferObject::create(['value' => $invalid]);
      $this->fail('An exception should be thrown for an invalid value.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame($message, $exception->getMessage());
    }

    $dto = DataTransferObject::create();
    try {
      $dto->set('value', $invalid);
      $this->fail('An exception should be thrown for an invalid value.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame($message, $exception->getMessage());
    }
  }

}
