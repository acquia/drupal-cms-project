<?php

/**
 * @file
 * Post update functions for Navigation Extra Tools.
 */

use Drupal\user\Entity\Role;

/**
 * Adds cache flushing and cron permissions for relevant roles.
 */
function navigation_extra_tools_post_update_update_permissions(): void {
  foreach (Role::loadMultiple() as $role) {
    if ($role->hasPermission('administer site configuration')) {
      $role->grantPermission('access navigation extra tools cache flushing')->save();
      $role->grantPermission('access navigation extra tools cron')->save();
    }
  }
}
