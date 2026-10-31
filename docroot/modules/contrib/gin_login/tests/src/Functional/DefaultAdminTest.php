<?php

declare(strict_types=1);

namespace Drupal\Tests\gin_login\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\OneTimeAuthentication;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Gin Login pages with Default Admin as the admin theme.
 *
 * Default Admin is the administration theme in Drupal core that is based on
 * Gin. It was added in Drupal 11.4.
 *
 * @group gin_login
 */
#[Group('gin_login')]
#[RunTestsInSeparateProcesses]
final class DefaultAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['gin_login'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!\Drupal::service('extension.list.theme')->exists('default_admin')) {
      $this->markTestSkipped('Default Admin is only available in Drupal 11.4 and later.');
    }
    \Drupal::service('theme_installer')->install(['default_admin']);
    $this->config('system.theme')->set('admin', 'default_admin')->save();
    // Let visitors create accounts, so the registration page is available.
    $this->config('user.settings')->set('register', 'visitors')->save();
  }

  /**
   * Tests that every Gin Login page uses the Gin Login page template.
   */
  public function testPages(): void {
    $assert_session = $this->assertSession();

    foreach (['user/login', 'user/password', 'user/register'] as $path) {
      $this->drupalGet($path);
      $assert_session->statusCodeEquals(200);
      $this->assertGinLoginPage();
    }

    // The one-time login link leads to the password reset form.
    $account = $this->drupalCreateUser();
    $this->drupalGet(\Drupal::service(OneTimeAuthentication::class)->generateOneTimeLoginUrl($account));
    $assert_session->addressMatches('#^/user/reset/#');
    $this->assertGinLoginPage();

    $this->drupalLogin($account);
    $this->drupalGet('user/logout/confirm');
    $this->assertGinLoginPage();
  }

  /**
   * Asserts that the current page uses the Gin Login page template.
   */
  private function assertGinLoginPage(): void {
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', 'body.gin-login .user-form-page__wallpaper');
    // Default Admin must not use its content edit form layout here.
    $assert_session->elementNotExists('css', 'body.edit-form');
  }

}
