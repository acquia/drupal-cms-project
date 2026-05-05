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
final class NavigationExtraToolsProjectBrowserTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation_extra_tools',
    'project_browser',
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
      'access project browser clear storage',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the Project Browser menu.
   */
  public function testProjectBrowserMenu(): void {
    // Test that Development menu now present under Tools.
    $this->assertSession()->elementExists('xpath', '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button")]/span[text() = "Project Browser"]');
    // Test that "Devel settings" exists as level 2 menu under Tools.
    $this->assertSession()->elementExists('xpath', '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Clear storage"]');
  }

  /**
   * Test clear storage.
   */
  public function testClearStorage(): void {
    // Test clicking "Clear storage".
    $this->clickLink('Clear storage');
    $this->assertSession()->responseContains('Project Browser storage cleared.');
  }

}
