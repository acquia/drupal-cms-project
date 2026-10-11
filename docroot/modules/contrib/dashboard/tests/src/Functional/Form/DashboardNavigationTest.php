<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional\Form;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\DashboardInterface;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\user\UserInterface;

/**
 * Test for dashboard navigation.
 */
#[Group('dashboard')]
class DashboardNavigationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dashboard', 'toolbar', 'node'];

  /**
   * A Dashboard to check access to.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected DashboardInterface $dashboard;

  /**
   * A user with permission to administer dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * A role id with permissions to administer dashboards.
   *
   * @var string
   */
  protected string $adminRole;

  /**
   * A role id with permissions to administer dashboards.
   *
   * @var string
   */
  protected string $toolbarRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing Dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $this->dashboard->save();

    $this->adminRole = $this->drupalCreateRole([
      'view existing_dashboard dashboard',
    ]);

    $this->toolbarRole = $this->drupalCreateRole([
      'view the administration theme',
      'access toolbar',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->adminRole);
    $this->adminUser->addRole($this->toolbarRole);
    $this->adminUser->save();

    // Ensure the front page is not the /user page.
    $this->config('system.site')->set('page.front', '/node')->save();
  }

  /**
   * Tests the existence of the dashboard navigation item.
   */
  public function testDashboardToolbarItem() {
    $this->drupalLogin($this->adminUser);

    // Assert that the dashboard navigation item is present in the HTML.
    $this->assertSession()->elementExists('css', '#toolbar-administration #toolbar-link-dashboard');

    // And it has the right cacheability.
    $this->drupalGet('<front>');
    // And we have the right cacheability.
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache-Tags', implode(' ', [
      'config:block_list',
      'config:dashboard_list',
      // We have a dependency on the menu.admin as administrator role present.
      'config:system.menu.admin',
      'config:system.theme',
      'config:views.view.frontpage',
      'http_response',
      'node_list',
      'rendered',
    ]));
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache-Contexts', implode(' ', [
      'languages:language_interface',
      'session',
      'theme',
      'url.query_args',
      'url.site',
      'user',
    ]));
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');

    // A second request will be a HIT.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');

    // And same for authenticated, but not admin user.
    $this->adminUser->removeRole($this->adminRole);
    $this->adminUser->save();

    $this->drupalGet('<front>');

    // Assert that the dashboard navigation item is not present in the HTML.
    $this->assertSession()->elementNotExists('css', '#toolbar-administration #toolbar-link-dashboard');

    // And we have the right cacheability.
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache-Tags', implode(' ', [
      'config:block_list',
      'config:dashboard_list',
      'config:system.theme',
      'config:views.view.frontpage',
      'http_response',
      'node_list',
      'rendered',
    ]));
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache-Contexts', implode(' ', [
      'languages:language_interface',
      'session',
      'theme',
      'url.query_args',
      'url.site',
      'user',
    ]));
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');

    // A second request will be a HIT.
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');
  }

}
