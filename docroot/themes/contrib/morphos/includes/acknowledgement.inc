<?php

/**
 * @file
 * Acknowledgement settings form.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the licence settings tab.
 */
function _theme_acknowledgement(&$form, FormStateInterface $form_state): void {
  $themeSettingsProvider = \Drupal::service('Drupal\Core\Extension\ThemeSettingsProvider');
  $subtheme_acknowledged = $themeSettingsProvider->getSetting('definition.subtheme_acknowledgement');
  $input = $form_state->getUserInput();

  if (empty($input['definition']['subtheme_acknowledgement']) && !$subtheme_acknowledged) {
    \Drupal::messenger()->addWarning(t('Morphos is currently set as the active theme. It is recommended to clone Morphos and use the cloned theme instead. <a href="@url" target="_blank">See the documentation</a> for more information.', [
      '@url' => 'https://docs.morpht.com/convivial-gov',
    ]));
  }
}
