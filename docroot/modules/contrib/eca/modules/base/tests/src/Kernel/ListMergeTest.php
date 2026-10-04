<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\DataProviderInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_list_combine" (List: merge) action plugin.
 */
#[Group('eca')]
#[Group('eca_base')]
#[RunTestsInSeparateProcesses]
class ListMergeTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
    'modeler_api',
    'field',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', 'users_data');
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
    $this->installConfig(static::$modules);
  }

  /**
   * Tests merging two plain, numerically-keyed arrays.
   */
  public function testMergePlainArrays(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('left', ['a', 'b']);
    $token_services->addTokenData('right', ['c', 'd']);
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();
    // The token service wraps the resulting array into a DataTransferObject,
    // so normalize it back to an array before asserting on the values.
    $merged = $token_services->getTokenData('merged');
    $this->assertInstanceOf(DataTransferObject::class, $merged);
    $this->assertSame(['a', 'b', 'c', 'd'], $merged->toArray());
  }

  /**
   * Tests merging associative arrays where right keys overwrite left keys.
   */
  public function testMergeAssociativeArraysOverwrite(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('left', ['x' => 1, 'y' => 2]);
    $token_services->addTokenData('right', ['y' => 3, 'z' => 4]);
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();
    // The right token's "y" overwrites the left token's "y".
    $merged = $token_services->getTokenData('merged');
    $this->assertInstanceOf(DataTransferObject::class, $merged);
    $this->assertSame(['x' => 1, 'y' => 3, 'z' => 4], $merged->toArray());
  }

  /**
   * Tests merging two DataTransferObject lists.
   */
  public function testMergeDataTransferObjects(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('left', DataTransferObject::create(['a', 'b']));
    $token_services->addTokenData('right', DataTransferObject::create(['c', 'd']));
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();
    $merged = $token_services->getTokenData('merged');
    $this->assertInstanceOf(DataTransferObject::class, $merged);
    $this->assertSame(['a', 'b', 'c', 'd'], $merged->toArray());
  }

  /**
   * Tests merging two \Traversable lists (ArrayIterator instances).
   */
  public function testMergeTraversable(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    // The token service cannot store a bare \Traversable (it tries to wrap it
    // into a DataTransferObject, which only accepts arrays). A data provider is
    // therefore used to expose the raw \ArrayIterator instances to the action.
    $token_services->addTokenDataProvider($this->createScalarDataProvider([
      'left' => new \ArrayIterator(['a', 'b']),
      'right' => new \ArrayIterator(['c', 'd']),
    ]));
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    // \ArrayIterator is \Traversable, so access is allowed.
    $this->assertTrue($action->access(NULL));
    $action->execute();
    $merged = $token_services->getTokenData('merged');
    $this->assertInstanceOf(DataTransferObject::class, $merged);
    $this->assertSame(['a', 'b', 'c', 'd'], $merged->toArray());
  }

  /**
   * Tests that access is forbidden when the left token is not iterable.
   */
  public function testAccessForbiddenLeftNotIterable(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    // The token service wraps scalar values added via addTokenData() into a
    // DataTransferObject (which is \Traversable). To expose a genuinely
    // non-iterable scalar to the action, a data provider is used instead.
    $token_services->addTokenDataProvider($this->createScalarDataProvider([
      'left' => 'not-a-list',
    ]));
    $token_services->addTokenData('right', ['c']);
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    $this->assertFalse($action->access(NULL));
    $access_result = $action->access(NULL, NULL, TRUE);
    $this->assertInstanceOf(AccessResultForbidden::class, $access_result);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * Tests that access is forbidden when the right token is not iterable.
   */
  public function testAccessForbiddenRightNotIterable(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('left', ['a']);
    // Provide a genuinely non-iterable scalar via a data provider, since
    // addTokenData() would otherwise wrap it into a traversable DTO.
    $token_services->addTokenDataProvider($this->createScalarDataProvider([
      'right' => 42,
    ]));
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    $this->assertFalse($action->access(NULL));
  }

  /**
   * Tests that access is allowed when both tokens are iterable.
   */
  public function testAccessAllowedBothIterable(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('left', ['a']);
    $token_services->addTokenData('right', ['b']);
    /** @var \Drupal\eca_base\Plugin\Action\ListMerge $action */
    $action = $action_manager->createInstance('eca_list_combine', [
      'merged_list' => 'merged',
      'list_token_left' => 'left',
      'list_token_right' => 'right',
    ]);
    $this->assertTrue($action->access(NULL));
    $this->assertTrue($action->access(NULL, NULL, TRUE)->isAllowed());
  }

  /**
   * Creates a token data provider that returns raw, unwrapped scalar values.
   *
   * @param array $data
   *   An associative array of token keys to raw scalar values.
   *
   * @return \Drupal\eca\Token\DataProviderInterface
   *   The data provider instance.
   */
  private function createScalarDataProvider(array $data): DataProviderInterface {
    return new class($data) implements DataProviderInterface {

      /**
       * Constructs the data provider.
       *
       * @param array $data
       *   An associative array of token keys to raw scalar values.
       */
      public function __construct(private array $data) {}

      /**
       * {@inheritdoc}
       */
      public function getData(string $key): mixed {
        return $this->data[$key] ?? NULL;
      }

      /**
       * {@inheritdoc}
       */
      public function hasData(string $key): bool {
        return array_key_exists($key, $this->data);
      }

    };
  }

}
