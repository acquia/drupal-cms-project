<?php

namespace Drupal\Tests\eca\Kernel\Model;

use Drupal\eca_base\Hook\BaseHooks;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Model test for entity loops.
 */
#[Group('eca')]
#[Group('eca_model')]
#[RunTestsInSeparateProcesses]
class EntityLoopTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'eca_base',
    'eca_content',
    'eca_user',
    'eca_views',
    'eca_test_model_entity_loop',
    'modeler_api',
  ];

  /**
   * Tests entity loop with the user list view.
   */
  public function testUserList(): void {
    // Create another user.
    $name = $this->randomMachineName();
    User::create([
      'uid' => 2,
      'name' => $name,
      'mail' => $name . '@localhost',
      'status' => TRUE,
    ])->save();

    \Drupal::classResolver(BaseHooks::class)->cron();
    $this->assertStatusMessages([
      'User ' . self::USER_1_NAME,
      "User $name",
    ]);
  }

}
