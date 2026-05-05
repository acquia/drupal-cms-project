<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the default block is installed for navigation.
 */
#[Group('dashboard')]
#[Group('navigation')]
class NavigationDefaultBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation', 'block', 'dashboard'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with permission to administer navigation blocks and access navigation.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user, log in and enable test navigation blocks.
    $this->adminUser = $this->drupalCreateUser([
      'access navigation',
      'administer blocks',
    ]);
    $dashboard = Dashboard::create([
      'id' => 'welcome',
      'label' => 'Welcome Dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $dashboard->save();

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests logic to include blocks in Navigation Layout UI.
   */
  public function testNavigationDefaultBlock(): void {
    if (version_compare(\Drupal::VERSION, '11.2', '<')) {
      $this->markTestSkipped('This will only work on >=11.2');
    }

    // The block is there, but we don't have access to any dashboard.
    $this->drupalGet('<front>');
    $this->assertSession()->linkNotExists('Dashboard');
    $this->assertSession()->linkByHrefNotExists('/admin/dashboard');

    /** @var \Drupal\user\RoleInterface $role */
    [$role_id] = $this->adminUser->getRoles(TRUE);
    $role = Role::load($role_id);
    $role->grantPermission('view welcome dashboard');
    $role->save();

    // Once we have access to a dashboard, we can use it.
    $this->drupalGet('<front>');
    $this->assertSession()->linkExists('Dashboard');
    $this->assertSession()->linkByHrefExists('/admin/dashboard');
    $this->assertSession()->elementExists('css', '.admin-toolbar');
    $this->assertSession()->elementContains('css', '.toolbar-button--icon--navigation-dashboard', 'Dashboard');
  }

}
