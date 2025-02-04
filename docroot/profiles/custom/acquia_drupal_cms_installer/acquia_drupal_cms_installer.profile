<?php

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;
use Drupal\RecipeKit\InstallerHooks;

/**
 * Implements hook_install_tasks().
 */
function acquia_drupal_cms_installer_install_tasks(): array {
  return InstallerHooks::installTasks();
}

/**
 * Implements hook_install_tasks_alter().
 */
function acquia_drupal_cms_installer_install_tasks_alter(array &$tasks, array $install_state): void {
  InstallerHooks::installTasksAlter($tasks, $install_state);
}

/**
 * Implements hook_form_alter().
 */
function acquia_drupal_cms_installer_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  InstallerHooks::formAlter($form, $form_state, $form_id);
}

/**
 * Implements hook_library_info_alter().
 */
function acquia_drupal_cms_installer_library_info_alter(array &$libraries, string $extension): void {
  InstallerHooks::libraryInfoAlter($libraries, $extension);
}

/**
 * Implements hook_theme_registry_alter().
 */
function acquia_drupal_cms_installer_theme_registry_alter(array &$hooks): void {
  InstallerHooks::themeRegistryAlter($hooks);
}

/**
 * Preprocess function for all theme hooks.
 */
function acquia_drupal_cms_installer_preprocess(array &$variables): void {
  InstallerHooks::preprocess($variables);
}
