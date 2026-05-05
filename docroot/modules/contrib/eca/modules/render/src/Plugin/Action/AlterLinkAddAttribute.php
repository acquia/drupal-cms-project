<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by adding an attribute.
 */
#[Action(
  id: 'eca_render_alter_link_add_attribute',
  label: new TranslatableMarkup('Render: alter link, add attribute'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by adding an attribute.'),
  version_introduced: '3.0.3',
)]
class AlterLinkAddAttribute extends AlterLinkBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->addAttribute(
      $this->tokenService->replaceClear($this->configuration['key']),
      $this->tokenService->replaceClear($this->configuration['value']),
      $this->configuration['reset'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'key' => '',
      'value' => '',
      'reset' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of attribute'),
      '#description' => $this->t('The name of the attribute to add to the link. Leave empty to not add an attribute when the attributes will be reset.'),
      '#default_value' => $this->configuration['key'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value of attribute'),
      '#description' => $this->t('The value of the attribute to add to the link. Leave empty to not add an attribute when the attributes will be reset.'),
      '#default_value' => $this->configuration['value'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset attributes'),
      '#description' => $this->t('If checked, the currently set attributes will be removed before adding the new attribute. Note that the class and the title attributes will not be reset.'),
      '#default_value' => $this->configuration['reset'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['reset'] = $form_state->getValue('reset');
    parent::submitConfigurationForm($form, $form_state);
  }

}
