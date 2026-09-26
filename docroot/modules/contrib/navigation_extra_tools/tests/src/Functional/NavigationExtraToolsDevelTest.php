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
final class NavigationExtraToolsDevelTest extends BrowserTestBase {

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
    'system',
  ];

  /**
   * Define constants for test assertions.
   */
  protected const DEVEL_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button")]/span[text() = "Development"]';
  protected const SETTINGS_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Devel settings"]';
  protected const CONFIG_EDIT_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Config editor"]';
  protected const REINSTALL_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Reinstall Modules"]';
  protected const REBUILD_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Rebuild Menu"]';

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
      'administer modules',
      'administer site configuration',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Install a module.
   *
   * @param string $name
   *   The name of the module to install.
   */
  protected function installModule(string $name) {
    $edit = [];
    $edit["modules[$name][enable]"] = $name;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');
  }

  /**
   * Install a module.
   *
   * @param string $name
   *   The name of the module to install.
   */
  protected function uninstallModule(string $name) {
    $edit = [];
    $edit["uninstall[$name]"] = $name;
    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm($edit, 'Uninstall');
    $this->assertSession()
      ->pageTextContains('The following modules will be completely uninstalled from your site');
    $this->submitForm([], 'Uninstall');
    $this->assertSession()
      ->pageTextContains('The selected modules have been uninstalled.');
  }

  /**
   * Test the Development menu.
   */
  public function testDevelopmentMenu(): void {
    // Test that Development menu now present under Tools.
    $this->assertSession()->elementExists('xpath', self::DEVEL_MENU_XPATH);
    // Test that "Devel settings" exists as level 2 menu under Tools.
    $this->assertSession()->elementExists('xpath', self::SETTINGS_MENU_XPATH);
    // Test that "Config editor" exists as level 2 menu under Tools.
    $this->assertSession()->elementExists('xpath', self::CONFIG_EDIT_MENU_XPATH);
    // Test that "Reinstall modules" exists as level 2 menu under Tools.
    $this->assertSession()->elementExists('xpath', self::REINSTALL_MENU_XPATH);
    // Test that "Rebuild menu" exists as level 2 menu under Tools.
    $this->assertSession()->elementExists('xpath', self::REBUILD_MENU_XPATH);

    // Uninstall navigation extra tools.
    $this->uninstallModule('navigation_extra_tools');
    // Verify that development menu no longer available.
    $this->assertSession()->elementNotExists('xpath', self::DEVEL_MENU_XPATH);
    // Reinstall navigation extra tools to test available when navigation extra
    // tools installed last.
    $this->installModule('navigation_extra_tools');
    // Verify that development menu available again.
    $this->assertSession()->elementExists('xpath', self::DEVEL_MENU_XPATH);
    // Check config editor menu available, to ensure getting added to menu.
    $this->assertSession()->elementExists('xpath', self::CONFIG_EDIT_MENU_XPATH);

    // Uninstall devel module.
    $this->uninstallModule('devel');
    // Verify that development menu gone.
    $this->assertSession()->elementNotExists('xpath', self::DEVEL_MENU_XPATH);
    // Reinstall devel to test menu available when devel installed after
    // navigation extra tools.
    $this->installModule('devel');
    // Verify that development menu returns.
    $this->assertSession()->elementExists('xpath', self::DEVEL_MENU_XPATH);
    // Check config editor menu available again.
    $this->assertSession()->elementExists('xpath', self::CONFIG_EDIT_MENU_XPATH);
  }

}
