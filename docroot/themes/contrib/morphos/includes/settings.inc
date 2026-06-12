<?php

/**
 * @file
 * Theme settings form.
 *
 * @see morphos_theme_settings_alter()
 */

use Drupal\Core\Form\FormStateInterface;

require_once dirname(__DIR__, 1) . '/includes/acknowledgement.inc';
require_once dirname(__DIR__, 1) . '/includes/licence.inc';
require_once dirname(__DIR__, 1) . '/theme_colors/theme_colors.inc';

/**
 * Attach theme settings form.
 */
function _morphos_theme_settings(&$form, FormStateInterface $form_state): void {

  // Create a section for theme settings.
  $form['morphos_settings'] = [
    '#type' => 'horizontal_tabs',
    '#title' => t('Theme settings'),
    '#description' => t('Configure options'),
    '#weight' => -100,
    '#open' => TRUE,
  ];

  // Warn if sub-theme acknowledgement has not been accepted.
  _theme_acknowledgement($form, $form_state);

  // Licence settings.
  _theme_licence($form, $form_state);

  // Theme colors settings.
  _theme_colors($form, $form_state);
}
