<?php

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;
use Drupal\RecipeKit\Installer\Hooks;

/**
 * Implements hook_install_tasks().
 */
function acquia_drupal_cms_installer_install_tasks(): array {
  return array_merge(
    ['acquia_drupal_cms_installer_tweak_config' => []],
    Hooks::installTasks(),
  );
}

/**
 * Implements hook_install_tasks_alter().
 */
function acquia_drupal_cms_installer_install_tasks_alter(array &$tasks, array $install_state): void {
  Hooks::installTasksAlter($tasks, $install_state);
}

/**
 * Implements hook_form_alter().
 */
function acquia_drupal_cms_installer_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  Hooks::formAlter($form, $form_state, $form_id);
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
