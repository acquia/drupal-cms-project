<?php

namespace Drupal\linkit_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form with linkit elements built with the Form API only.
 */
class LinkitFormElementTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'linkit_form_element_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $linkit_profile_id = NULL) {
    $autocomplete = [
      '#type' => 'linkit',
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => $linkit_profile_id,
      ],
    ];

    // A linkit element with no related inputs.
    $form['button_link'] = [
      '#title' => 'Button link',
    ] + $autocomplete;

    // A linkit element that is the "href" attribute itself, as in link
    // dialogs.
    $form['attributes'] = [
      '#tree' => TRUE,
    ];
    $form['attributes']['href'] = [
      '#title' => 'Dialog link',
    ] + $autocomplete;
    foreach (['data-entity-type', 'data-entity-uuid', 'data-entity-substitution'] as $attribute) {
      $form['attributes'][$attribute] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];
    }

    // Two linkit elements shaped like link field widgets, which must each
    // write only to their own attributes.
    foreach (['link_a', 'link_b'] as $link) {
      $form[$link] = [
        '#tree' => TRUE,
      ];
      $form[$link]['uri'] = [
        '#title' => $link,
      ] + $autocomplete;
      foreach (['href', 'data-entity-type', 'data-entity-uuid', 'data-entity-substitution'] as $attribute) {
        $form[$link]['attributes'][$attribute] = [
          '#type' => 'hidden',
          '#default_value' => '',
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus('button_link: ' . $form_state->getValue('button_link'));
    foreach ($form_state->getValue('attributes') as $attribute => $value) {
      $this->messenger()->addStatus("attributes[$attribute]: $value");
    }
    foreach (['link_a', 'link_b'] as $link) {
      $this->messenger()->addStatus("{$link}[uri]: " . $form_state->getValue([$link, 'uri']));
      foreach ($form_state->getValue([$link, 'attributes']) as $attribute => $value) {
        $this->messenger()->addStatus("{$link}[attributes][$attribute]: $value");
      }
    }
  }

}
