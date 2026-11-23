<?php

namespace Drupal\Tests\navigation_extra_tools\Functional\Update;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

// cSpell:ignore toolshelp
/**
 * Tests update functions for the Block Content module.
 *
 * @group navigation_extra_tools
 */
#[Group('navigation_extra_tools')]
#[RunTestsInSeparateProcesses]
class UpdateDevelMenuTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    // Use the newest dump the running core can update from. Dumps made before
    // Drupal 11.4 store lazy param converter service IDs in the router table,
    // which fail on 11.4 and later until the router is rebuilt. See
    // https://www.drupal.org/project/drupal/issues/3612050. Drupal 12 can only
    // update from Drupal 11.3 or newer.
    if (version_compare(\Drupal::VERSION, '11.4-dev', '>=')) {
      $this->databaseDumpFiles = [
        __DIR__ . '/../../../fixtures/update/drupal-11.4.13-navigation_extra_tools-1.2.php.gz',
      ];
    }
    elseif (version_compare(\Drupal::VERSION, '11.1', '>=')) {
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
  protected const DEVEL_MENU_XPATH = '//li[contains(@id, "navigation-extra-toolshelp")]/div/ul/li[contains(@class, "toolbar-menu__item--level-1")]/button[contains(@class, "toolbar-button")]/span[text() = "Development"]';
  protected const CONFIG_EDIT_MENU_XPATH = '//li[contains(@id, "navigation-extra-toolshelp")]/div/ul/li/ul/li[contains(@class, "toolbar-menu__item--level-2")]/a[contains(@class, "toolbar-menu__link--2") and text() = "Config editor"]';

  /**
   * Tests update hook moves 'More' settings into more array.
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
