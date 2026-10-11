<?php

namespace Drupal\drupal_cms_helper;

/**
 * Events for which telemetry is collected.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at
 *   any time without warning. External code should not interact with this
 *   class.
 */
enum Telemetry: string {

  // Drupal was installed from a site template.
  // @see \Drupal\drupal_cms_helper\EventSubscriber\RecipeSubscriber
  case SiteTemplateInstall = 'site_template_install';

  // A module was installed.
  // @see \Drupal\drupal_cms_helper\Hook\TelemetryHooks::onModuleInstall()
  case ModuleInstall = 'module_install';

  // A module was uninstalled.
  // @see \Drupal\drupal_cms_helper\Hook\TelemetryHooks::onModuleUninstall()
  case ModuleUninstall = 'module_uninstall';

  // A ping to let the telemetry server know the site still exists.
  // @see \Drupal\drupal_cms_helper\Hook\TelemetryHooks::cron()
  case Ping = 'ping';

  // A recipe was applied.
  // @see \Drupal\drupal_cms_helper\EventSubscriber\RecipeSubscriber
  case RecipeApplied = 'recipe_apply';

  // A specific administrative page was visited.
  // @see \Drupal\drupal_cms_helper\EventSubscriber\TelemetrySubscriber
  case AdminPage = 'admin_page';

  // A Canvas code component was created.
  // @see \Drupal\drupal_cms_helper\Hook\TelemetryHooks::onComponentSave()
  case CodeComponent = 'code_component';

  // A user logged in.
  // @see \Drupal\drupal_cms_helper\Hook\TelemetryHooks::onLogin()
  case Login = 'user_login';

  // The user opts the site into telemetry.
  // @see \Drupal\drupal_cms_helper\TelemetryManager::status()
  case OptIn = 'opt_in';

}
