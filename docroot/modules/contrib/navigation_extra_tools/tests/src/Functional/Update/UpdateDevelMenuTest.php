<?php

namespace Drupal\Tests\navigation_extra_tools\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

// cSpell:ignore toolshelp

/**
 * Tests update functions for the Block Content module.
 *
 * @group block_content
 */
class UpdateDevelMenuTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    if (version_compare(\Drupal::VERSION, '11.1', '>=')) {
      $this->databaseDumpFiles = [
        __DIR__ . '/../../../fixtures/update/drupal-11.1.8-navigation_extra_tools-1.2.php.gz',
      ];
    }
    else {
      $this->databaseDumpFiles = [
        __DIR__ . '/../../../fixtures/update/drupal-10.5.2-navigation_extra_tools-1.2.php.gz',
      ];
    }
  }

  /**
   * Define constants for test assertions.
   */
  protected const DEVEL_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button")]/span[text() = "Development"]';
  protected const CONFIG_EDIT_MENU_XPATH = '//li[@id="navigation-link-navigation-extra-toolshelp"]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Config editor"]';

  /**
   * Tests update hook moves 'More' settings into more array.
   *
   * @test
   */
  public function testUpdateDevelMenu(): void {
    $adminUser = $this->drupalCreateUser();
    $adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $adminUser->save();
    $this->drupalLogin($adminUser);

    $this->runUpdates();

    $this->drupalGet('<front>');
    // Verify that development menu exists.
    $this->assertSession()->elementExists('xpath', self::DEVEL_MENU_XPATH);
    // Check config editor menu available.
    $this->assertSession()->elementExists('xpath', self::CONFIG_EDIT_MENU_XPATH);
  }

}
