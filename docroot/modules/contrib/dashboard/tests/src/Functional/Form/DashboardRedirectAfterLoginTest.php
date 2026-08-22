<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional\Form;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Test for dashboard redirects after login.
 */
#[Group('dashboard')]
class DashboardRedirectAfterLoginTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dashboard'];

  /**
   * A user with permission to view dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A role id with permissions to view dashboards.
   *
   * @var string
   */
  protected $role;

  /**
   * A Dashboard to check access to.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected $dashboard;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $this->dashboard->save();

    $this->role = $this->drupalCreateRole([
      'view the administration theme',
      'view existing_dashboard dashboard',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block');

    // Use the login form by default; the one-time login link flow is exercised
    // explicitly in ::testDashboardRedirectFromOneTimeLoginLink().
    $this->useOneTimeLoginLinks = FALSE;
  }

  /**
   * Tests behavior when there is a dashboard available.
   */
  public function testDashboardRedirectWhenDashboardAvailable() {
    $this->drupalLogin($this->adminUser);

    $this->assertSession()->addressEquals('/admin/dashboard');
  }

  /**
   * Tests behavior when there is no dashboard.
   */
  public function testDashboardRedirectWhenThereIsNoDashboard() {
    $this->dashboard->delete();
    $this->drupalLogin($this->adminUser);

    $this->assertSession()->addressNotEquals('/admin/dashboard');
  }

  /**
   * Tests behavior when there is no dashboard available.
   */
  public function testDashboardRedirectWhenThereIsNoEnabledDashboard() {
    $this->dashboard->setStatus(FALSE)->save();
    $this->drupalLogin($this->adminUser);

    $this->assertSession()->addressNotEquals('/admin/dashboard');
  }

  /**
   * Tests behavior when logged in user cannot access to any dashboard.
   */
  public function testDashboardRedirectWhenThereIsNoPermission() {
    $this->drupalLogin($this->createUser([]));

    $this->assertSession()->addressNotEquals('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests behavior when there is destination query parameter.
   */
  public function testDashboardRedirectWhenThereIsDestination() {
    $this->drupalGet(Url::fromRoute('user.login', [], [
      'query' => ['destination' => 'foo'],
    ]));
    $this->submitForm([
      'name' => $this->adminUser->getAccountName(),
      'pass' => $this->adminUser->passRaw,
    ], 'Log in');

    $this->assertSession()->addressEquals('/foo');
  }

  /**
   * Tests behavior from one time login link.
   */
  public function testDashboardRedirectFromOneTimeLoginLink() {
    // Log in via a one-time login link instead of the login form. The link
    // carries a destination, which must take precedence over the dashboard
    // redirect.
    $this->useOneTimeLoginLinks = TRUE;
    $this->drupalLogin($this->adminUser);

    $this->assertSession()->addressEquals('/user/' . $this->adminUser->id());
  }

}
