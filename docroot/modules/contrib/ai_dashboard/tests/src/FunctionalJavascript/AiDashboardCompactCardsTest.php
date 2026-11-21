<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the rendered "Features" project cards are compact.
 *
 * The project cards themselves are rendered client-side by the
 * project_browser JavaScript app, so a real browser is needed to verify
 * that the compacting CSS actually takes effect once they appear.
 *
 * @see https://www.drupal.org/project/ai_dashboard/issues/3567899
 */
#[Group('ai_dashboard')]
#[RunTestsInSeparateProcesses]
class AiDashboardCompactCardsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai_dashboard'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Point the "Features" source at the module's own bundled recipe list
    // instead of the remote one, so the cards render deterministically and
    // without depending on network access.
    $recipes_file = \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('ai_dashboard') . '/ai_dashboard_recommended_recipes.yml';
    $this->assertFileExists($recipes_file, 'The bundled recipe fixture used by this test could not be found.');
    $project_browser_settings = $this->config('project_browser.admin_settings');
    $enabled_sources = $project_browser_settings->get('enabled_sources') ?? [];
    $enabled_sources['ai_dashboard_recommended'] = [
      'uri' => $recipes_file,
      'ttl' => 86400,
    ];
    $project_browser_settings->set('enabled_sources', $enabled_sources)->save();

    // Permissions aren't under test here; use an admin account so block and
    // endpoint access can't be the reason the cards fail to render.
    $account = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($account);
  }

  /**
   * Tests that rendered project cards pick up the compacting CSS.
   */
  public function testProjectCardsRenderCompact(): void {
    // project_browser only renders the grid layout (as opposed to a plain
    // list) above a 1200px-wide viewport; force that before navigating so
    // the "pb-projects-grid"/"pb-project--grid" markup this test checks is
    // actually the markup that gets rendered.
    $this->getSession()->resizeWindow(1400, 1000, 'current');

    $this->drupalGet('/admin/config/ai');
    $assert_session = $this->assertSession();

    // The mount point is rendered server-side; if it's missing, the block
    // itself never displayed and there's no point waiting for JS-rendered
    // cards.
    $assert_session->elementExists('css', '#project-browser');

    $assert_session->waitForElementVisible('css', '#project-browser .pb-project', 20000);
    $card = $this->getSession()->getPage()->find('css', '#project-browser .pb-project');
    $this->assertNotNull($card, 'At least one project card should be rendered.');

    // The grid should use the narrower minimum card width, so more cards
    // fit on a row instead of stretching a handful of oversized ones.
    $min_width = $this->getSession()->evaluateScript(
      "getComputedStyle(document.querySelector('#project-browser .pb-projects-grid')).getPropertyValue('--grid-item--min-width').trim()"
    );
    $this->assertSame('220px', $min_width);

    // The description should be clamped instead of growing the card to fit
    // arbitrarily long text.
    $line_clamp = $this->getSession()->evaluateScript(
      "getComputedStyle(document.querySelector('#project-browser .pb-project__body')).webkitLineClamp"
    );
    $this->assertSame('3', $line_clamp);
  }

}
