<?php

namespace Drupal\Tests\bpmn_io\TestSite;

use Drupal\TestSite\TestSetupInterface;

/**
 * Prepare the test-site to be installed.
 */
class TestSiteInstallTestScript implements TestSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    $module_installer = \Drupal::service('module_installer');
    $module_installer->install([
      'bpmn_io',
      'bpmn_io_test',
      'eca',
      'eca_base',
      'eca_content',
      'eca_ui',
      'eca_user',
      'eca_views',
      'navigation',
      'user',
      'views',
    ]);

    $theme_installer = \Drupal::service('theme_installer');
    $theme_installer->install(['claro']);
    $system_theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $system_theme_config->set('default', 'claro')->save();
  }

}
