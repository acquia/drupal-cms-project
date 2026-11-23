<?php

/**
 * @file
 * Post-update functions for the Haven theme.
 */

use Drupal\Core\Extension\ModuleInstallerInterface;

/**
 * Uninstalls the CVA module, which is no longer needed.
 */
function haven_theme_post_update_uninstall_cva(): void {
  \Drupal::service(ModuleInstallerInterface::class)->uninstall(['cva']);
}
