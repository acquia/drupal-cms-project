<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation_extra_tools\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

// cSpell:ignore toolshelp

/**
 * Test description.
 *
 * @group navigation_extra_tools
 */
final class NavigationExtraToolsAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'devel',
    'navigation_extra_tools',
  ];

  /**
   * Define constants for test assertions.
   */
  protected const TOOLS_MENU_XPATH = '//button[contains(@class, "toolbar-button--icon--navigation-extra-tools-help") or contains(@class, "toolbar-button--icon--wrench")]/span[text() = "Tools"]';
  protected const CACHE_MENU_XPATH = '//li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button--expand--down")]/span[text() = "Flush individual cache"]';
  protected const DEVEL_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button")]/span[text() = "Development"]';

  /**
   * A test user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * A test user who can access the navigation toolbar but nothing else.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $navigationUser;

  /**
   * A test user who can run cron, but not see other navigation tools.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $cronUser;

  /**
   * A test user who can access the navigation toolbar, devel and site admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $devUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'access navigation',
      'access administration pages',
      'administer software updates',
      'access navigation extra tools cache flushing',
      'access navigation extra tools cron',
    ]);

    // Create navigation user.
    $this->navigationUser = $this->drupalCreateUser([
      'access navigation',
    ]);

    // Create cron user.
    $this->cronUser = $this->drupalCreateUser([
      'access navigation',
      'access navigation extra tools cron',
    ]);

    // Create navigation user.
    $this->devUser = $this->drupalCreateUser([
      'access navigation',
      'access devel information',
    ]);

    // Log in administrative user.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test that Tools menu only visible for user who can see functions under it.
   */
  public function testToolsMenuVisible() {
    // Check that user with admin rights can see Tools menu but not development.
    $this->assertSession()->elementExists('xpath', self::TOOLS_MENU_XPATH);
    $this->assertSession()->elementExists('xpath', self::CACHE_MENU_XPATH);
    $this->assertSession()->elementNotExists('xpath', self::DEVEL_MENU_XPATH);
    // Test that user with access to Navigation but no admin functions cannot
    // see Tools menu.
    $this->drupalLogin($this->navigationUser);
    $this->assertSession()->elementNotExists('xpath', self::TOOLS_MENU_XPATH);
    $this->assertSession()->elementNotExists('xpath', self::CACHE_MENU_XPATH);
    $this->assertSession()->elementNotExists('xpath', self::DEVEL_MENU_XPATH);
    // Check that user with run Cron permission can see Tools menu but not flush
    // caches or  development.
    $this->drupalLogin($this->cronUser);
    $this->assertSession()->elementExists('xpath', self::TOOLS_MENU_XPATH);
    $this->assertSession()->elementNotExists('xpath', self::CACHE_MENU_XPATH);
    $this->assertSession()->elementNotExists('xpath', self::DEVEL_MENU_XPATH);
    // Test that user with devel access.
    $this->drupalLogin($this->devUser);
    $this->assertSession()->elementExists('xpath', self::TOOLS_MENU_XPATH);
    $this->assertSession()->elementNotExists('xpath', self::CACHE_MENU_XPATH);
    $this->assertSession()->elementExists('xpath', self::DEVEL_MENU_XPATH);
  }

}
