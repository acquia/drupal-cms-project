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

  /**
   * Identifies a custom module.
   */
  public const string CUSTOM_MODULE = '**custom**';

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

  /**
   * A list of site templates that are reported to telemetry.
   *
   * This list is explicitly documented in Drupal CMS's user guide.
   *
   * @see docs/src/updates/telemetry.md
   */
  public const array SITE_TEMPLATES = [
    'archimedes',
    'byte',
    'caresphere',
    'convene',
    'convivial_gov',
    'dashi',
    'drupal_cms_*',
    'educare',
    'everbright',
    'forma',
    'goodwell',
    'haven',
    'healthcare',
    'horizonaid',
    'hourglass_inc',
    'local',
    'lupus_decoupled_starter',
    'mercury_demo',
    'pulse',
    'provus_edu',
    'rightup',
    'site_templates_meridian_charter',
    'summit',
    'varbase_*',
  ];

  /**
   * A list of contributed recipes that are reported to telemetry.
   *
   * This list is explicitly documented in Drupal CMS's user guide.
   *
   * @see docs/src/updates/telemetry.md
   */
  public const array CONTRIB_RECIPES = [
    'ai_*',
    'commerce_*',
    'drupal_cms_*',
    'govnl_cms_*',
    'lupus_decoupled_*',
    'mailchimp_signup_forms',
    'stripe_webform_donation',
    'varbase_*',
  ];

  /**
   * A list of core recipes that are reported to telemetry.
   *
   * This list is explicitly documented in Drupal CMS's user guide.
   *
   * @see docs/src/updates/telemetry.md
   */
  public const array CORE_RECIPES = [
    'administrator_role',
    'audio_media_type',
    'basic_block_type',
    'basic_html_format_editor',
    'comment_base',
    'content_editor_role',
    'core_recommended_admin_theme',
    'core_recommended_maintenance',
    'core_recommended_performance',
    'document_media_type',
    'editorial_workflow',
    'example',
    'full_html_format_editor',
    'image_media_type',
    'local_video_media_type',
    'remote_video_media_type',
    'restricted_html_format',
    'standard',
    'standard_responsive_images',
    'tags_taxonomy',
    'user_picture',
  ];

}
