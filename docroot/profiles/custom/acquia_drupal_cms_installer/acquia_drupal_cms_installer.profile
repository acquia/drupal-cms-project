<?php

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;
use Drupal\RecipeKit\Installer\Hooks;

/**
 * Implements hook_install_tasks().
 */
function acquia_drupal_cms_installer_install_tasks(): array {
  return Hooks::installTasks();
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
