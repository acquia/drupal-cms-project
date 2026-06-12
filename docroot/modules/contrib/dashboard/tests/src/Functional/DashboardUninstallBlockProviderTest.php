<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\announce_feed_test\AnnounceTestHttpClientMiddleware;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Remove block functionality.
 */
#[Group('dashboard')]
class DashboardUninstallBlockProviderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dashboard_test',
    'dashboard',
    'announcements_feed',
    'announce_feed_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A role id with permissions to administer dashboards.
   *
   * @var string
   */
  protected $role;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->role = $this->drupalCreateRole([
      'view the administration theme',
      'administer dashboard',
      'configure any layout',
      'view test dashboard',
      'access announcements',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'primary_local_tasks']);
    AnnounceTestHttpClientMiddleware::setAnnounceTestEndpoint('/announce-feed-json/community-feeds');

  }

  /**
   * Tests the remove block logic.
   */
  public function testDashboardRemoveBlock() {
    // Login with adequate permissions.
    $this->drupalLogin($this->adminUser);

    // Check that test dashboard exists.
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementExists('css', '.dashboard--test');

    // Validate that 2 blocks are visible and no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->assertSession()->pageTextNotContains("You have unsaved changes.");
    $this->assertSession()->pageTextContains("Dashboard Text 1");
    $this->assertSession()->pageTextContains("Dashboard Text 2");

    // Add the announcements block.
    $page = $this->getSession()->getPage();
    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->pressButton('Add section');
    $page->clickLink('Add block');
    $page->clickLink('Announcements');

    $this->submitForm([
      'settings[label]' => 'Announcements from Drupal.org',
      'settings[label_display]' => TRUE,
    ], 'Add block');
    $page->pressButton('Save dashboard layout');

    // Confirm that text block is added and config stored.
    $this->assertSession()->statusMessageContains('Updated dashboard Test layout.', 'status');

    // Validate that one block is added.
    $this->assertSession()->pageTextContains("Announcements from Drupal.org");

    // On uninstall, validate that the dashboard still exists, but the provider
    // block is removed and there are no unsaved changes.
    $this->container->get('module_installer')->uninstall(['announcements_feed']);
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->assertSession()->pageTextNotContains("You have unsaved changes.");
    $this->assertSession()->pageTextNotContains("Announcements from Drupal.org");
    $this->assertSession()->pageTextContains("Dashboard Text 1");
    $this->assertSession()->pageTextContains('Dashboard Text 2');
  }

}
