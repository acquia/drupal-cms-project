<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests a block with a form functionality.
 */
#[Group('dashboard')]
class DashboardFormBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dashboard',
    'layout_builder_form_block_test',
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

    $dashboard = Dashboard::create([
      'id' => 'dashboard_with_block_form',
      'label' => 'Dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $dashboard->save();

    $this->role = $this->drupalCreateRole([
      'view the administration theme',
      "view {$dashboard->id()} dashboard",
      'administer dashboard',
      'configure any layout',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'primary_local_tasks']);
  }

  /**
   * Tests a block with a form.
   */
  public function testDashboardFormBlock() {
    $this->drupalLogin($this->adminUser);

    // Validate that there are no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/dashboard_with_block_form/layout');
    $this->assertSession()->pageTextNotContains('You have unsaved changes.');
    $this->assertSession()->pageTextNotContains('Form block 1');
    $this->assertSession()->pageTextNotContains('Form block 2');

    // Add one section.
    $page = $this->getSession()->getPage();
    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->pressButton('Add section');

    // Add one block.
    $page->clickLink('Add block');
    $page->clickLink('Layout Builder form block test form api form block');
    $this->submitForm([
      'settings[label]' => 'Form block 1',
    ], 'Add block');
    $page->pressButton('Save dashboard layout');
    // Validate that one block has been added, and there are no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/dashboard_with_block_form/layout');
    $this->assertSession()->pageTextNotContains('You have unsaved changes.');
    $this->assertSession()->pageTextContains('Form block 1');
    $this->assertSession()->pageTextNotContains('Form block 2');

    $this->drupalGet('admin/dashboard');
    $this->assertSession()->pageTextContains('Form block 1');
    $this->assertSession()->pageTextNotContains('Form block 2');

    // Add a second block.
    $this->drupalGet('/admin/structure/dashboard/dashboard_with_block_form/layout');
    $page = $this->getSession()->getPage();
    $page->clickLink('Add block');
    $page->clickLink('Layout Builder form block test form api form block');
    $this->submitForm([
      'settings[label]' => 'Form block 2',
    ], 'Add block');
    $page->pressButton('Save dashboard layout');
    // Validate that one block has been added, and there are no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/dashboard_with_block_form/layout');
    $this->assertSession()->pageTextNotContains('You have unsaved changes.');
    $this->assertSession()->pageTextContains('Form block 1');
    $this->assertSession()->pageTextContains('Form block 2');

    $this->drupalGet('admin/dashboard');
    $this->assertSession()->pageTextContains('Form block 1');
    $this->assertSession()->pageTextContains('Form block 2');

    // Remove one block.
    $this->drupalGet('/admin/structure/dashboard/dashboard_with_block_form/layout');
    $page = $this->getSession()->getPage();
    // Extract block UUID using data-layout-block-uuid.
    $blockUuid = $page->find('css', '[data-layout-block-uuid]')
      ->getAttribute('data-layout-block-uuid');
    $this->drupalGet("/layout_builder/remove/block/dashboard/dashboard_with_block_form/0/first/{$blockUuid}");
    $this->submitForm([], 'Remove');
    $page->pressButton('Save dashboard layout');
    // Validate that one block is removed, and there are no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/dashboard_with_block_form/layout');
    $this->assertSession()->pageTextNotContains('You have unsaved changes.');
    $this->assertSession()->pageTextNotContains('Form block 1');
    $this->assertSession()->pageTextContains('Form block 2');

    $this->drupalGet('admin/dashboard');
    $this->assertSession()->pageTextNotContains('Form block 1');
    $this->assertSession()->pageTextContains('Form block 2');
  }

}
