<?php

namespace Drupal\ai_dashboard\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Form\ModulesListForm;

/**
 * Contains module list reduced to modules from given packages.
 */
class PackageFilteredModulesListForm extends ModulesListForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $packages = []) {
    $form = parent::buildForm($form, $form_state);
    if (!empty($packages)) {
      foreach ($form['modules'] as $package => $modules) {
        if (str_starts_with($package, '#')) {
          continue;
        }
        if (!in_array($package, $packages)) {
          unset($form['modules'][$package]);
        }
        elseif (isset($form['modules'][$package]['#open'])) {
          $form['modules'][$package]['#open'] = FALSE;
        }
      }
    }
    return $form;
  }

}
