<?php

namespace Drupal\eca_base\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;

/**
 * Plugin implementation of the ECA condition for key value store values.
 */
#[EcaCondition(
  id: 'eca_state',
  label: new TranslatableMarkup('ECA state: compare'),
  description: new TranslatableMarkup('Compares a value from ECA\'s own persistent key value store against a given value. This is not Drupal\'s state service; use <em>Key value store: read</em> with the collection "state" to reach that.'),
  version_introduced: '1.0.0',
)]
class EcaState extends StringComparisonBase {

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    return $this->state->get($this->configuration['key'], '');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->configuration['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'key' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#default_value' => $this->configuration['key'],
      '#weight' => -90,
      '#description' => $this->t("The key of the value in ECA's key value store."),
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -70,
      '#description' => $this->t('The value to compare the stored value with.'),
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
