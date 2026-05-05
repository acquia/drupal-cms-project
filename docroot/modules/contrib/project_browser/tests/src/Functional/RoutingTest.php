<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Core\Url;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests routing of source plugins.
 *
 * @group project_browser
 */
#[Group('project_browser')]
final class RoutingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'project_browser_test_mock' => [],
      ])
      ->save(TRUE);
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
    ]));
  }

  /**
   * Tests sources before and after enabling them.
   */
  public function testSources(): void {
    $assert_session = $this->assertSession();

    $url = Url::fromRoute('project_browser.browse', [
      'source' => 'drupal_core',
    ]);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(404);

    // Enable another source plugin and ensure that the enabled source handler
    // is aware of it.
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'project_browser_test_mock' => [],
        'drupal_core' => [],
      ])
      ->save();

    $enabled_source_ids = array_keys($this->container->get(ProjectBrowserSourceManager::class)->getAllEnabledSources());
    sort($enabled_source_ids);
    $expected = [
      'drupal_core',
      'project_browser_test_mock',
    ];
    $this->assertSame($expected, $enabled_source_ids);

    foreach ($enabled_source_ids as $plugin_id) {
      $url = Url::fromRoute('project_browser.browse', [
        'source' => $plugin_id,
      ]);
      $this->drupalGet($url);
      $assert_session->statusCodeEquals(200);
    }
  }

  /**
   * Tests visiting the UI without specifying a source.
   */
  public function testDefaultSource(): void {
    $this->config('project_browser.admin_settings')
      ->set('default_source', 'project_browser_test_mock')
      ->save();

    $url = Url::fromRoute('project_browser.browse');
    $this->drupalGet($url);
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    // We should have been redirected.
    $assert_session->addressEquals('/admin/modules/browse/project_browser_test_mock');

    // If the default source is not an enabled source, we should get a 404.
    $this->config('project_browser.admin_settings')
      ->set('default_source', 'nonsense')
      ->save();
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(404);

    // If there's no default source, we should get a 404.
    $this->config('project_browser.admin_settings')
      ->clear('default_source')
      ->save();
    $this->getSession()->reload();
    $assert_session->statusCodeEquals(404);
  }

}
