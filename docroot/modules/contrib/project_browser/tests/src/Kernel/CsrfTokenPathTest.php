<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\CsrfTokenPath;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests CSRF URL generation for Project Browser routes.
 *
 * @group project_browser
 * @covers \Drupal\project_browser\CsrfTokenPath
 */
#[Group('project_browser')]
#[RunTestsInSeparateProcesses]
final class CsrfTokenPathTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests that activate URLs get a real path-bound CSRF token.
   */
  public function testActivateUrlHasValidToken(): void {
    $url = Url::fromRoute('project_browser.activate');
    $string = $this->container->get(CsrfTokenPath::class)->toString($url);

    $query = [];
    parse_str(parse_url($string, PHP_URL_QUERY) ?: '', $query);
    $this->assertNotEmpty($query['token']);

    $path = 'admin/modules/project_browser/activate';
    $this->assertNotSame(Crypt::hashBase64($path), $query['token']);
    $this->assertTrue(
      $this->container->get('csrf_token')->validate($query['token'], $path),
    );
  }

  /**
   * Tests that uninstall URLs get a real path-bound CSRF token.
   */
  public function testUninstallUrlHasValidToken(): void {
    $url = Url::fromRoute('project_browser.uninstall', ['name' => 'node']);
    $string = $this->container->get(CsrfTokenPath::class)->toString($url);

    $query = [];
    parse_str(parse_url($string, PHP_URL_QUERY) ?: '', $query);
    $this->assertNotEmpty($query['token']);

    $path = 'project-browser/uninstall/node';
    $this->assertNotSame(Crypt::hashBase64($path), $query['token']);
    $this->assertTrue(
      $this->container->get('csrf_token')->validate($query['token'], $path),
    );
  }

}
