<?php

/**
 * @file
 * Licence settings form.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the licence settings tab.
 */
function _theme_licence(&$form, FormStateInterface $form_state): void {
  $themeSettingsProvider = \Drupal::service('Drupal\Core\Extension\ThemeSettingsProvider');
  $licence_url = 'https://docs.morpht.com/convivial-gov';
  $licence_accepted = $themeSettingsProvider->getSetting('licence.licence_accept');

  $form['licence'] = [
    '#type' => 'details',
    '#title' => t('Licence'),
    '#group' => 'morphos_settings',
    '#tree' => TRUE,
  ];

  $input = $form_state->getUserInput();
  if (empty($input['licence']['licence_accept']) && !$licence_accepted) {
    \Drupal::messenger()->addWarning(t('The Morphos licence has not been accepted. Please navigate to the <strong>Licence</strong> tab to review and accept the licence. <a href="@url" target="_blank">Purchase a licence</a>.', [
      '@url' => $licence_url,
    ]));
  }

  $form['licence']['purchase_info'] = [
    '#type' => 'markup',
    '#markup' => '<hr /><p>' . t('A production licence is required before deploying Morphos to a live site. <a href="@url" target="_blank">Purchase a licence</a>.', [
      '@url' => $licence_url,
    ]) . '</p><hr />',
  ];

  $licence_path = __DIR__ . '/../LICENSE.txt';
  $licence_text = file_exists($licence_path) ? file_get_contents($licence_path) : t('Licence file not found.');
  $form['licence']['licence_text'] = [
    '#type' => 'markup',
    '#markup' => '<pre><code>' . htmlspecialchars($licence_text) . '</code></pre>',
  ];

  $form['licence']['licence_accept'] = [
    '#type' => 'checkbox',
    '#title' => t('Accept'),
    '#description' => t('I have reviewed and accept the licence.'),
    '#default_value' => $licence_accepted ?? FALSE,
  ];
}
