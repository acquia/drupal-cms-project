<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional\Form;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Test for dashboard form.
 */
#[Group('dashboard')]
class DashboardFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'block_test',
    'dashboard',
    'dashboard_test',
    'user',
  ];

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
      'view test dashboard',
      'administer dashboard',
      'configure any layout',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'primary_local_tasks']);
  }

  /**
   * Tests dashboard add form behavior.
   */
  public function testDashboardAdd() {
    $this->drupalLogin($this->adminUser);

    // Create dashboard to edit.
    $edit = [];
    $edit['id'] = strtolower($this->randomMachineName(8));
    $edit['label'] = $this->randomString(8);
    $edit['description'] = $this->randomString(16);
    $edit['status'] = TRUE;

    $this->drupalGet('admin/structure/dashboard/add');
    $this->assertSession()->checkboxChecked('edit-status');
    $this->submitForm($edit, 'Save');

    // Check that the title and body fields are displayed with expected values.
    $this->assertSession()->pageTextContains("Created new dashboard {$edit['label']}");

    /** @var \Drupal\dashboard\Entity\Dashboard $dashboard */
    $dashboard = \Drupal::entityTypeManager()->getStorage('dashboard')->load($edit['id']);
    // We already have a dashboard in dashboard_test module with -10 as weight.
    // So our weight should be higher.
    $this->assertEquals(-9, $dashboard->getWeight());
  }

  /**
   * Adding a block with a required context to check context mapping.
   */
  public function testDashboardAddContextBlock() {
    $this->drupalLogin($this->adminUser);
    $block_label = 'Member for block';
    $user_name = $this->adminUser->getAccountName();

    // Load the test dashboard (in test module config).
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->assertSession()->pageTextNotContains($block_label);
    $this->assertSession()->pageTextNotContains($user_name);

    // Add a user context block.
    $edit = [];
    $edit['settings[label]'] = $block_label;
    $edit['settings[label_display]'] = TRUE;
    $edit['settings[context_mapping][user]'] = '@user.current_user_context:current_user';

    $this->drupalGet('/layout_builder/add/block/dashboard/test/0/first/test_context_aware');
    $this->submitForm($edit, 'Add block');

    // Save changes to dashboard.
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->submitForm([], 'Save dashboard layout');

    // Check the block renders correctly.
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->pageTextContains($block_label);
    $this->assertSession()->pageTextContains($user_name);
  }

  /**
   * Tests dashboard form local tasks.
   */
  public function testDashboardFormLocalTasks() {
    $dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing',
      'weight' => 0,
    ]);
    $dashboard->save();

    $this->drupalLogin($this->adminUser);

    // Check local actions in Edit form.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a[contains(@class, is-active)]', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Preview');

    // Check local actions in Edit layout form.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard/layout');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a[contains(@class, is-active)]', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Preview');

    // Check local actions in Preview tab.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard/preview');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a[contains(@class, is-active)]', 'Preview');
  }

  /**
   * Tests saving dashboard layout.
   */
  public function testSaveDashboardLayout() {
    $this->drupalLogin($this->adminUser);

    $dashboard = Dashboard::create([
      'id' => 'test_dashboard',
      'label' => 'Test dashboard',
      'weight' => 0,
    ]);
    $dashboard->save();

    $this->drupalGet('admin/structure/dashboard/test_dashboard/layout');
    $this->submitForm([], 'Save dashboard layout');

    $this->assertSession()->addressEquals('/admin/dashboard/test_dashboard');
    $this->assertSession()->pageTextContains('Updated dashboard Test Dashboard layout.');
  }

  /**
   * Tests discarding dashboard layout changes.
   */
  public function testDiscardDashboardLayout() {
    $confirmation_message = 'Any unsaved changes to the layout will be discarded. This action cannot be undone.';
    if (version_compare(\Drupal::VERSION, '11.3', '<')) {
      $confirmation_message = 'Are you sure you want to discard your layout changes?';
    }

    $this->drupalLogin($this->adminUser);

    $dashboard = Dashboard::create([
      'id' => 'test_dashboard',
      'label' => 'Test dashboard',
      'weight' => 0,
    ]);
    $dashboard->save();
    $this->drupalGet('admin/structure/dashboard/test_dashboard/layout');

    $this->clickLink('Discard changes');

    $this->assertSession()->addressEquals('/admin/structure/dashboard/test_dashboard/layout/discard-changes');
    $this->assertSession()->pageTextContains($confirmation_message);

    $this->assertSession()->elementExists('xpath', '//input[@type="submit" and @value="Confirm"]');
    $this->submitForm([], 'Confirm');

    $this->assertSession()->addressEquals('/admin/dashboard/test_dashboard');
    $this->assertSession()->pageTextContains('The changes to the layout have been discarded.');
  }

}
