<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional;

use Drupal\dashboard\Plugin\Block\PlaceholderBlock;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a block with a placeholder functionality can be edited.
 */
#[Group('dashboard')]
#[CoversMethod(PlaceholderBlock::class, 'buildConfigurationForm')]
#[CoversMethod(PlaceholderBlock::class, 'validateConfigurationForm')]
#[CoversMethod(PlaceholderBlock::class, 'submitConfigurationForm')]
#[RunTestsInSeparateProcesses]
class DashboardPlaceholderBlockTest extends BrowserTestBase {

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
      'id' => 'test_dashboard',
      'status' => TRUE,
    ]);
    $dashboard->appendSection(new Section('layout_onecol'));
    $dashboard->save();

    $this->adminUser = $this->drupalCreateUser([
      'view the administration theme',
      "view {$dashboard->id()} dashboard",
      'administer dashboard',
      'configure any layout',
    ]);
  }

  /**
   * Tests a block with a placeholder block can be edited.
   */
  public function testDashboardFormPlaceholderBlock(): void {
    $this->drupalLogin($this->adminUser);

    // Add a placeholder block, now using a config action.
    \Drupal::service('plugin.manager.config_action')
      ->applyAction('addComponentToLayout', 'dashboard.dashboard.test_dashboard', [
        'section' => 0,
        'position' => 1,
        'component' => [
          'region' => [
            'layout_onecol' => 'content',
          ],
          'configuration' => [
            'id' => 'dashboard_placeholder',
            'decorates' => 'dashboard_text_block',
            'label' => 'Dashboard Placeholder Block via Config Action',
            'label_display' => 'visible',
            'text' => [
              'value' => 'This block was added via a config action, and uses a placeholder',
              'format' => 'plain_text',
            ],
            'additional' => [],
          ],
        ],
      ]);

    // Ensure it appears as expected.
    $this->drupalGet('/admin/dashboard/test_dashboard');
    $this->assertSession()->pageTextContains('Dashboard Placeholder Block via Config Action');
    $this->assertSession()->pageTextContains('This block was added via a config action, and uses a placeholder');

    // First we need to find the uuid of the LB section component we added.
    $dashboard = Dashboard::load('test_dashboard');
    $uuid = array_find(
      $dashboard->getSection(0)->getComponentsByRegion('content'),
      fn (SectionComponent $component): bool => $component->getPluginId() === 'dashboard_placeholder',
    )?->getUuid();
    $this->assertIsString($uuid);

    // Ensure we can edit the placeholder block.
    $this->drupalGet("layout_builder/update/block/dashboard/{$dashboard->id()}/0/content/$uuid");
    $this->submitForm([
      'settings[label]' => 'Edited placeholder label',
      'settings[label_display]' => 'visible',
      'settings[text][value]' => 'My new text',
    ], 'Update');
    $this->assertSession()->addressEquals("/admin/structure/dashboard/{$dashboard->id()}/layout");
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->submitForm([], 'Save dashboard layout');

    // Ensure it appears as expected.
    $this->drupalGet("/admin/dashboard/{$dashboard->id()}");
    $this->assertSession()->pageTextNotContains('Dashboard Placeholder Block via Config Action');
    $this->assertSession()->pageTextNotContains('This block was added via a config action, and uses a placeholder');
    $this->assertSession()->pageTextContains('Edited placeholder label');
    $this->assertSession()->pageTextContains('My new text');
  }

}
