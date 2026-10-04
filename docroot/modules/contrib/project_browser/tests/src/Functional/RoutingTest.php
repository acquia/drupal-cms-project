<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Core\Url;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\project_browser\Traits\SynchronizeCsrfTokenSeedTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests routing of source plugins.
 *
 * @group project_browser
 */
#[Group('project_browser')]
#[RunTestsInSeparateProcesses]
final class RoutingTest extends BrowserTestBase {

  use SynchronizeCsrfTokenSeedTrait;

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
      ->save();
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

    $enabled_source_ids = array_keys(\Drupal::service(ProjectBrowserSourceManager::class)->getAllEnabledSources());
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

  /**
   * Tests CSRF protection on activate and uninstall routes.
   */
  public function testActivationRoutes(): void {
    $assert_session = $this->assertSession();

    // Missing token is rejected.
    $url = Url::fromRoute('project_browser.activate', [], [
      'query' => [
        'projects' => ['drupal_core/ban'],
        '_wrapper_format' => 'drupal_ajax',
      ],
    ]);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(403);

    $url = Url::fromRoute('project_browser.uninstall', ['name' => 'ban']);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(403);

    // With a valid path-bound token, CSRF access is granted (may still 4xx for
    // other reasons such as missing project data — just not 403 from CSRF).
    $csrf_token = $this->container->get('csrf_token');
    $this->drupalGet(Url::fromRoute('project_browser.activate', [], [
      'query' => [
        'projects' => ['drupal_core/ban'],
        '_wrapper_format' => 'drupal_ajax',
        'token' => $csrf_token->get('admin/modules/project_browser/activate'),
      ],
    ]));
    $assert_session->statusCodeNotEquals(403);

    $this->drupalGet(Url::fromRoute('project_browser.uninstall', ['name' => 'ban'], [
      'query' => [
        'return_to' => Url::fromRoute('<front>')->toString(),
        'token' => $csrf_token->get('project-browser/uninstall/ban'),
      ],
    ]));
    $assert_session->statusCodeNotEquals(403);
  }

}
