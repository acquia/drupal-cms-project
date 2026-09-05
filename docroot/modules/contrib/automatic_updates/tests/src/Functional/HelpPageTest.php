<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[RunTestsInSeparateProcesses]
class HelpPageTest extends AutomaticUpdatesFunctionalTestBase {

  use AssertPreconditionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'help',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the help page for Automatic Updates loads correctly.
   */
  public function testHelpPage(): void {
    $permissions = [
      'access administration pages',
      'access help pages',
    ];
    $user = $this->createUser($permissions);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/help/automatic_updates');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Automatic Updates');
  }

}
