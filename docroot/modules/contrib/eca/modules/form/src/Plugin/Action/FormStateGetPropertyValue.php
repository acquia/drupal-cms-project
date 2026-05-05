<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get a property value of the form state.
 */
#[Action(
  id: 'eca_form_state_get_property_value',
  label: new TranslatableMarkup('Form state: get property value'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Get a property value of the current form state in scope and set it as a token.'),
  version_introduced: '1.0.0',
)]
class FormStateGetPropertyValue extends FormStatePropertyActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }
    $token = $this->tokenService;

    $property_name = $this->normalizePropertyPath($token->replace($this->configuration['property_name']));
    if (empty($property_name)) {
      return;
    }
    $name = explode('.', $property_name);
    // An enforced "eca" key is being used at root level at
    // FormStateSetPropertyValue, therefore lookup there first.
    $eca_name = array_merge(['eca'], $name);
    $value = $form_state->has($eca_name) ? $form_state->get($eca_name) : $form_state->get($name);
    if ($value !== NULL) {
      $token->addTokenData($this->configuration['token_name'], $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The name of the token the property value should be stored into.'),
      '#required' => TRUE,
      '#weight' => -49,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
