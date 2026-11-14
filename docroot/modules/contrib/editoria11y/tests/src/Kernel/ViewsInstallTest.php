<?php

namespace Drupal\Tests\editoria11y\Kernel;

use Drupal\Component\Uuid\Uuid;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the module's optional views are installed correctly.
 *
 * @group editoria11y
 */
#[RunTestsInSeparateProcesses]
class ViewsInstallTest extends KernelTestBase {

  /**
   * The views the module provides in config/optional.
   */
  protected const VIEW_IDS = ['editoria11y_dismissals', 'editoria11y_results'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The config importer rejects a sync directory without system.site.
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    // User module clears its data for each module that is uninstalled.
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Views are created as config entities when the module is installed.
   */
  public function testViewsInstalledAsConfigEntities(): void {
    $this->container->get('module_installer')->install(['editoria11y']);

    foreach (static::VIEW_IDS as $view_id) {
      $view = View::load($view_id);
      $this->assertNotNull($view, "View $view_id is installed.");
      // A view written as raw config instead of through the entity API has
      // no UUID.
      $this->assertTrue(Uuid::isValid((string) $view->uuid()), "View $view_id has a valid UUID.");
      $this->assertContains('editoria11y', $view->getDependencies()['module']);
    }
  }

  /**
   * A config sync can install the module alongside a display extender.
   *
   * Regression test: views.settings may list a display extender from a module
   * that the import installs after editoria11y. If the views exist before
   * that module is installed, any router rebuild in between initializes
   * their page displays and fails with a PluginNotFoundException.
   */
  public function testConfigSyncWithDisplayExtender(): void {
    $modules = ['editoria11y_test_extender', 'editoria11y_test_router', 'editoria11y', 'views'];
    $module_installer = $this->container->get('module_installer');

    // Build a sync directory as a site using the extender would export it.
    $module_installer->install(['editoria11y_test_extender']);
    $this->config('views.settings')
      ->set('display_extenders', ['editoria11y_test_extender'])
      ->save();
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($this->container->get('config.storage'), $sync);
    // An exported view always has a UUID. Add one if the install path under
    // test failed to create it, so that this test only covers the import.
    $expected_uuids = [];
    foreach (static::VIEW_IDS as $view_id) {
      $data = $sync->read("views.view.$view_id");
      $data['uuid'] ??= $this->container->get('uuid')->generate();
      $sync->write("views.view.$view_id", $data);
      $expected_uuids[$view_id] = $data['uuid'];
    }

    // Return to a site without those modules, then import the sync directory.
    // Views does not remove an uninstalled module's display extender from its
    // settings, and deleting the editoria11y views would then fail on it, so
    // clear it from active config only. The sync directory keeps it.
    $this->config('views.settings')->set('display_extenders', [])->save();
    $module_installer->uninstall($modules);
    // Views is uninstalled too, so check storage rather than loading entities.
    $active = $this->container->get('config.storage');
    foreach (static::VIEW_IDS as $view_id) {
      $this->assertFalse($active->exists("views.view.$view_id"), "View $view_id was removed on uninstall.");
    }
    // The importer installs modules in batches and enables a whole batch
    // before invoking hook_install(). Sites with many modules split
    // editoria11y and the extender's module across batches; install one
    // module per batch to reproduce that here.
    $this->setSetting('core.multi_module_install_batch_size', 1);
    $this->configImporter()->import();

    $this->assertSame([], $this->configImporter()->getErrors());
    foreach (static::VIEW_IDS as $view_id) {
      $view = View::load($view_id);
      $this->assertNotNull($view, "View $view_id is imported.");
      $this->assertSame($expected_uuids[$view_id], $view->uuid(), "View $view_id is created from the sync directory.");
    }
  }

}
