<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation_extra_tools\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\RequirementsPageTrait;
use Drupal\user\UserInterface;

// cSpell:ignore toolshelp

/**
 * Test description.
 *
 * @group navigation_extra_tools
 */
final class NavigationExtraToolsMenuTest extends BrowserTestBase {

  use RequirementsPageTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation_extra_tools',
  ];

  /**
   * A test user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'access navigation',
      'access administration pages',
      'administer software updates',
      'access navigation extra tools cache flushing',
      'access navigation extra tools cron',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test callback.
   */
  public function testToolsMenu(): void {
    // Test for tools menu.
    $this->assertSession()->elementExists('xpath', '//button[contains(@class, "toolbar-button--icon--navigation-extra-tools-help") or contains(@class, "toolbar-button--icon--wrench")]/span[text() = "Tools"]');
    // Test for flush all caches menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-1")]/a[contains(@class, "toolbar-button")]/span[text() = "Flush all caches"]');
    // Test for flush individual caches submenu.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button--expand--down")]/span[text() = "Flush individual cache"]');
    // Test for Flush CSS and JavaScript menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Flush CSS and JavaScript"]');
    // Test for Flush plugins cache menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Flush plugins cache"]');
    // Test for Flush render cache menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Flush render cache"]');
    // Test for Flush routing and links cache menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Flush routing and links cache"]');
    // Test for Flush static cache menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Flush static cache"]');
    // Test for Flush twig cache menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Flush twig cache"]');
    // Test for Rebuild theme registry menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Rebuild theme registry"]');
    // Test for Run cron menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-1")]/a[contains(@class, "toolbar-button")]/span[text() = "Run cron"]');
    // Test for Run updates menu option.
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "toolbar-menu__item--level-1")]/a[contains(@class, "toolbar-button")]/span[text() = "Run updates"]');
  }

  /**
   * Test flush cache.
   */
  public function testFlushCache(): void {
    // Test clicking "Flush all caches".
    $this->clickLink('Flush all caches');
    $this->assertSession()->responseContains('All caches cleared.');
  }

  /**
   * Test flush CSS and JavaScript cache.
   */
  public function testFlushCssJs(): void {
    // Test clicking "Flush CSS/JS caches".
    $this->clickLink('Flush CSS and JavaScript');
    $this->assertSession()->responseContains('CSS and JavaScript cache cleared.');
  }

  /**
   * Test flush Plugins cache.
   */
  public function testFlushPlugins(): void {
    // Test clicking "Flush plugins caches".
    $this->clickLink('Flush plugins cache');
    $this->assertSession()->responseContains('Plugins cache cleared.');
  }

  /**
   * Test flush Render cache.
   */
  public function testFlushRender(): void {
    // Test clicking "Flush render caches".
    $this->clickLink('Flush render cache');
    $this->assertSession()->responseContains('Render cache cleared.');
  }

  /**
   * Test flush Routing and links cache.
   */
  public function testFlushRoutingAndLinks(): void {
    // Test clicking "Flush routing and links caches".
    $this->clickLink('Flush routing and links cache');
    $this->assertSession()->responseContains('Routing and links cache cleared.');
  }

  /**
   * Test flush Static cache.
   */
  public function testFlushStatic(): void {
    // Test clicking "Flush static caches".
    $this->clickLink('Flush static cache');
    $this->assertSession()->responseContains('Static cache cleared.');
  }

  /**
   * Test flush Twig cache.
   */
  public function testFlushTwig(): void {
    // Test clicking "Flush twig caches".
    $this->clickLink('Flush twig cache');
    $this->assertSession()->responseContains('Twig cache cleared.');
  }

  /**
   * Test flush Twig cache.
   */
  public function testRebuildThemeRegistry(): void {
    // Test clicking "Rebuild theme registry".
    $this->clickLink('Rebuild theme registry');
    $this->assertSession()->responseContains('Theme registry rebuilt.');
  }

  /**
   * Test running cron.
   */
  public function testRunCron(): void {
    // Test clicking "Run cron".
    $this->clickLink('Run cron');
    $this->assertSession()->responseContains('Cron ran successfully.');
  }

  /**
   * Test running updates.
   */
  public function testRunUpdates(): void {
    // Test clicking "Run updates".
    $this->clickLink('Run updates');
    // Fix requirements for unsupported PHP versions.
    $this->updateRequirementsProblem();
    $this->assertSession()->responseContains('Drupal database update');
  }

  /**
   * Test the Development menu.
   */
  public function testDevelopmentMenuNotEnabled(): void {
    // Check Development menu not shown when devel not enabled.
    $this->assertSession()->elementNotExists('xpath', '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button")]/span[text() = "Development"]');
  }

  /**
   * Test Navigation libraries not loaded for anonymous user.
   */
  public function testNavigationLibraryInclusion(): void {
    // Verify navigation JS present for admin user.
    $this->assertSession()->responseContains('navigation/js/toolbar-dropdown.js');
    $this->assertSession()->responseContains('navigation/js/admin-toolbar-wrapper.js');
    $this->assertSession()->responseContains('navigation/js/arrow-navigation.js');
    $this->drupalLogout();
    // Verify navigation JS not loaded for anonymous user - fix issue 3515628.
    $this->assertSession()->responseNotContains('navigation/js/toolbar-dropdown.js');
    $this->assertSession()->responseNotContains('navigation/js/admin-toolbar-wrapper.js');
    $this->assertSession()->responseNotContains('navigation/js/arrow-navigation.js');
  }

}
