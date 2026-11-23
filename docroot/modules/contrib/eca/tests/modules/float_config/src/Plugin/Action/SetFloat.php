<?php

namespace Drupal\eca_test_float_config\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * An action carrying a float typed configuration key.
 *
 * The action does nothing on purpose. It exists so that its configuration
 * schema section is visited by hook_config_schema_info_alter(), which is what
 * retypes a "float" key to "eca_float_or_token".
 *
 * @see \Drupal\eca\Hook\ConfigSchemaHooks::configSchemaInfoAlter()
 */
#[Action(
  id: 'eca_test_float_config_set',
  label: new TranslatableMarkup('Float config: set'),
)]
#[EcaAction(
  description: new TranslatableMarkup('This action carries a float typed configuration key.'),
  no_docs: TRUE,
)]
class SetFloat extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'ratio' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['ratio'] = [
      '#type' => 'textfield',
      '#default_value' => $this->configuration['ratio'],
      '#title' => $this->t('Ratio'),
      '#eca_token_replacement' => TRUE,
      '#weight' => 10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['ratio'] = $form_state->getValue('ratio');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    // Intentionally empty: only the configuration schema matters here.
  }

}
