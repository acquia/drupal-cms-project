<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\system\Traits\OffCanvasTestTrait;

/**
 * Tests a block with a form functionality with js interaction.
 */
#[Group('dashboard')]
class DashboardFormBlockTest extends WebDriverTestBase {

  use OffCanvasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dashboard',
    'layout_builder',
    'layout_discovery',
    'layout_builder_form_block_test',
    'off_canvas_test',
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
    $layout_url = '/admin/structure/dashboard/dashboard_with_block_form/layout';
    $this->drupalLogin($this->adminUser);

    // Edit the layout and add a block that contains a form.
    $this->drupalGet($layout_url);
    $this->addSection();
    $this->openAddBlockForm('Layout Builder form block test form api form block');
    $this->getSession()->getPage()->checkField('settings[label_display]');

    // Save the new block, and ensure it is displayed on the page.
    $this->getSession()->getPage()->pressButton('Add block');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->assertNoElementAfterWait('css', '#drupal-off-canvas');
    $this->assertSession()->addressEquals($layout_url);
    $this->assertSession()->pageTextContains('Layout Builder form block test form api form block');
    $this->getSession()->getPage()->pressButton('Save dashboard layout');

    $unexpected_save_message = 'You have unsaved changes';
    $expected_save_message = 'Updated dashboard Dashboard layout';
    $this->assertSession()->statusMessageNotContains($unexpected_save_message);
    $this->assertSession()->statusMessageContains($expected_save_message);

    // Try to save the layout again and confirm it can save because there are no
    // nested form tags.
    $this->drupalGet($layout_url);
    $this->getSession()->getPage()->pressButton('Save dashboard layout');
    $this->assertSession()->statusMessageNotContains($unexpected_save_message);
    $this->assertSession()->statusMessageContains($expected_save_message);
  }

  /**
   * Opens the add section in the off-canvas dialog.
   */
  private function addSection(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickLink('Add section');
    $assert_session->assertWaitOnAjaxRequest();
    $this->waitForOffCanvasArea();
    $assert_session->linkExists('One column');
    $page->clickLink('One column');
    $this->assertOffCanvasFormAfterWait('layout_builder_configure_section');
    $assert_session->buttonExists('Add section');
    $page->pressButton('Add section');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Remove Section 1');
  }

  /**
   * Opens the add block form in the off-canvas dialog.
   *
   * @param string $block_title
   *   The block title which will be the link text.
   *
   * @todo move this from into a trait from
   *   \Drupal\Tests\layout_builder\FunctionalJavascript\LayoutBuilderTest
   */
  private function openAddBlockForm($block_title): void {
    $this->assertSession()->linkExists('Add block');
    $this->clickLink('Add block');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('named', ['link', $block_title]));
    $this->clickLink($block_title);
    $this->assertOffCanvasFormAfterWait('layout_builder_add_block');
  }

  /**
   * Waits for the specified form and returns it when available and visible.
   *
   * @param string $expected_form_id
   *   The expected form ID.
   *
   * @todo move this from into a trait from
   *    \Drupal\Tests\layout_builder\FunctionalJavascript\LayoutBuilderTest
   */
  private function assertOffCanvasFormAfterWait(string $expected_form_id): void {
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->waitForOffCanvasArea();
    $off_canvas = $this->assertSession()->elementExists('css', '#drupal-off-canvas');
    $this->assertNotNull($off_canvas);
    $form_id_element = $off_canvas->find('hidden_field_selector', ['hidden_field', 'form_id']);
    // Ensure the form ID has the correct value and that the form is visible.
    $this->assertNotEmpty($form_id_element);
    $this->assertSame($expected_form_id, $form_id_element->getValue());
    $this->assertTrue($form_id_element->getParent()->isVisible());
  }

}
