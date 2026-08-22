<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_list_sort" action plugin.
 */
#[Group('eca')]
#[Group('eca_base')]
#[RunTestsInSeparateProcesses]
class ListSortTest extends KernelTestBase {

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
   * Data provider for the list sort test case.
   *
   * @return array[]
   *   Test case data for in-place and copy based sorting.
   */
  public static function listSortProvider(): array {
    return [
      'without dest token' => [''],
      'with dest token' => ['dest_token'],
    ];
  }

  /**
   * Tests the "eca_list_sort" action plugin.
   */
  #[DataProvider('listSortProvider')]
  public function testListSortDestToken(string $dest_token): void {

    $chaos = [
      'Img12.png' => 'Img12.png',
      'img1.png' => 'img1.png',
      'img10.png' => 'img10.png',
      'Img10.png' => 'Img10.png',
      'Img2.png' => 'Img2.png',
      'img12.png' => 'img12.png',
      'img2.png' => 'img2.png',
      'Img1.png' => 'Img1.png',
    ];

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    /** @var \Drupal\eca\Plugin\Action\ListSortBase $action */
    $action = $action_manager->createInstance('eca_list_sort', []);

    foreach (array_keys($action->getSupportedSortMethods()) as $method) {

      $action->setConfiguration([
        'list_token' => 'list',
        'sort_method' => $method,
        'token_name' => $dest_token,
      ]);
      $token_services->addTokenData(
        $action->getConfiguration()['list_token'],
        DataTransferObject::create($chaos)
      );

      // Copy the unsorted list into a new variable, then sort it.
      $expected = $chaos;
      $method($expected);

      $action->execute();

      $dest_token = trim((string) $action->getConfiguration()['token_name']);
      if (!$dest_token) {
        $dest_token = trim((string) $action->getConfiguration()['list_token']);
      }

      $list = $token_services->getTokenData($dest_token);

      $this->assertSame($expected, $list->toArray());

      // Reset the input token data.
      $token_services->clearTokenData();
    }
  }

}
