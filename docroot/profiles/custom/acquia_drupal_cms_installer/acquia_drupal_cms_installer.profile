<?php

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\RecipeKit\Installer\Hooks;
use Drupal\RecipeKit\Installer\Messenger;

/**
 * Implements hook_install_tasks().
 */
function acquia_drupal_cms_installer_install_tasks(array &$install_state): array {
  return array_merge(
    ['acquia_drupal_cms_installer_tweak_config' => []],
    Hooks::installTasks($install_state),
  );
}

/**
 * Implements hook_install_tasks_alter().
 */
function acquia_drupal_cms_installer_install_tasks_alter(array &$tasks, array $install_state): void {
  Hooks::installTasksAlter($tasks, $install_state);

    // The recipe kit doesn't change the title of the batch job that applies all
    // the recipes, so to override it, we use core's custom string overrides.
    // We can't use the passed-in $install_state here, because it isn't passed by
    // reference.
    $langcode = $GLOBALS['install_state']['parameters']['langcode'];
    $settings = Settings::getAll();
    // @see install_profile_modules()
    $settings["locale_custom_strings_$langcode"]['']['Installing @drupal'] = (string) t('Setting up your site');
    new Settings($settings);
}

/**
 * Implements hook_form_alter().
 */
function acquia_drupal_cms_installer_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  Hooks::formAlter($form, $form_state, $form_id);
}

/**
 * Implements hook_form_alter() for install_configure_form.
 */
function acquia_drupal_cms_installer_form_install_configure_form_alter(array &$form): void {
    // We always install Automatic Updates, so we don't need to expose the update
    // notification settings.
    $form['update_notifications']['#access'] = FALSE;
}

/**
 * Tell Package Manager about acquia/drupal-recommended-settings.
 *
 * @return void
 */
function acquia_drupal_cms_installer_tweak_config(): void {
  $config = \Drupal::configFactory()->getEditable('package_manager.settings');

  $additional_trusted_composer_plugins = array_merge(
    $config->get('additional_trusted_composer_plugins'),
    ['acquia/drupal-recommended-settings']
  );

  $config->set(
    'additional_trusted_composer_plugins',
    $additional_trusted_composer_plugins
  );
  $config->save();
}
