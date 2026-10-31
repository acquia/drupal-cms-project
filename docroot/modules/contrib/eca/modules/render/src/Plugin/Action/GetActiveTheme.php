<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\FormFieldMachineName;

/**
 * Get the active theme.
 */
#[Action(
  id: 'eca_get_active_theme',
  label: new TranslatableMarkup('Get active theme'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Get the currently active theme and store the value as a token.'),
  version_introduced: '1.1.0',
)]
class GetActiveTheme extends ActiveThemeActionBase {

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
      '#maxlength' => 1024,
      '#element_validate' => [[FormFieldMachineName::class, 'validateElementsMachineName']],
      '#title' => $this->t('Token name'),
      '#description' => $this->t('Specify the name of the token, that holds the name of the currently active theme.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -25,
      '#required' => TRUE,
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

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->tokenService->addTokenData($this->configuration['token_name'], $this->themeManager->getActiveTheme()->getName());
  }

}
