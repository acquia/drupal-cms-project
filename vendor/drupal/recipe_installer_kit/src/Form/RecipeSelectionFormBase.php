<?php

declare(strict_types=1);

namespace Drupal\RecipeKit\Installer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\RecipeKit\Installer\FormInterface as InstallerFormInterface;

abstract class RecipeSelectionFormBase extends FormBase implements InstallerFormInterface {

  /**
   * @return iterable<string, array{name: string, description?: string, packages: string[]}>
   */
  abstract protected function getChoices(): iterable;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['add_ons'] = [
      '#type' => 'checkboxes',
    ];
    foreach ($this->getChoices() as $key => $choice) {
      $form['add_ons']['#options'][$key] = $choice['name'];

      if (isset($choice['description'])) {
        $form['add_ons'][$key]['#description'] = $choice['description'];
      }
    }
    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
        '#op' => 'submit',
      ],
      'skip' => [
        '#type' => 'submit',
        '#value' => $this->t('Skip this step'),
        '#op' => 'skip',
      ],
      '#type' => 'actions',
    ];
    $form['#title'] = '';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;

    $choices = $form_state->getValue('add_ons');
    if ($choices) {
      $all_choices = $this->getChoices();

      // `add_ons` may be a string (only one item was chosen), so coerce it
      // an array.
      $choices = array_filter((array) $choices);
      foreach ($choices as $key) {
        foreach ($all_choices[$key]['packages'] as $name) {
          $install_state['parameters']['recipes'][] = $name;
        }
      }
    }
    else {
      $install_state['parameters']['recipes'] = $install_state['profile_info']['recipes']['default'] ?? [];
    }
  }

}
