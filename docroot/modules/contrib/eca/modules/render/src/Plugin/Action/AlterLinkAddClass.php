<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by adding a class to its attributes.
 */
#[Action(
  id: 'eca_render_alter_link_add_class',
  label: new TranslatableMarkup('Render: alter link, add class'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by adding a class to its attributes.'),
  version_introduced: '3.0.3',
)]
class AlterLinkAddClass extends AlterLinkBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->addClass($this->tokenService->replaceClear($this->configuration['class']), $this->configuration['reset']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'class' => '',
      'reset' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Class'),
      '#description' => $this->t('The class to add to the link. Leave empty to not add a class when the classes will be reset.'),
      '#default_value' => $this->configuration['class'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset classes'),
      '#description' => $this->t('If checked, the currently set classes will be removed before adding the new class.'),
      '#default_value' => $this->configuration['reset'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['class'] = $form_state->getValue('class');
    $this->configuration['reset'] = $form_state->getValue('reset');
    parent::submitConfigurationForm($form, $form_state);
  }

}
