<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the AI Dashboard renders a compact, scannable layout.
 *
 * @see https://www.drupal.org/project/ai_dashboard/issues/3567899
 */
#[Group('ai_dashboard')]
#[RunTestsInSeparateProcesses]
class AiDashboardCompactUiTest extends BrowserTestBase {

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

    // Disable CSS aggregation so the assertions below can rely on the
    // stylesheet being served, and its rules being readable, verbatim.
    $this->config('system.performance')
      ->set('css.preprocess', FALSE)
      ->save();

    // The "Features" block pulls its cards from a remote curated list.
    // Point it at the module's own copy instead, so the test exercises
    // real card markup without depending on network access.
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
   * Tests that the compacting stylesheet is attached to the dashboard.
   */
  public function testCompactStylesheetIsAttached(): void {
    $this->drupalGet('/admin/config/ai');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--ai-dashboard');

    // The "Features" block mounts the (JavaScript-rendered) project cards
    // here; the library that compacts those cards must be on the page.
    $this->assertSession()->elementExists('css', '#project-browser');
    $this->assertSession()->responseMatches('/ai-dashboard\.css/');
  }

  /**
   * Tests that the shipped CSS keeps the project cards compact.
   *
   * Guards against regressions to the rules that keep the "Features"
   * section cards small enough to scan at a glance: a narrower minimum
   * card width (so more cards fit per row), tighter padding/gaps, and a
   * clamped description so cards of differing text length don't end up
   * wildly different heights.
   */
  public function testProjectCardsAreCompact(): void {
    $css_path = \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('ai_dashboard') . '/css/ai-dashboard.css';
    $css = file_get_contents($css_path);
    $this->assertIsString($css);

    $this->assertMatchesRegularExpression(
      '/\.pb-projects-grid\s*\{[^}]*--grid-item--min-width:\s*220px;/',
      $css,
      'Project cards should use a narrower minimum width so more fit per row.'
    );
    $this->assertMatchesRegularExpression(
      '/\.pb-project\s*\{[^}]*padding:\s*var\(--gin-spacing-s\);/',
      $css,
      'Project cards should use tighter padding than the default.'
    );
    $this->assertMatchesRegularExpression(
      '/\.pb-project__body\s*\{[^}]*-webkit-line-clamp:\s*3;/',
      $css,
      'The project description should be clamped so card heights stay consistent.'
    );
  }

}
