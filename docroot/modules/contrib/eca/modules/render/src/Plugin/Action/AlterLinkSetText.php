<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by setting its text.
 */
#[Action(
  id: 'eca_render_alter_link_set_text',
  label: new TranslatableMarkup('Render: alter link, set visible text'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by setting its visible text.'),
  version_introduced: '3.0.3',
)]
class AlterLinkSetText extends AlterLinkBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->setText(Markup::create($this->tokenService->replaceClear($this->configuration['text'])));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'text' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Visible text'),
      '#description' => $this->t('The visible text of the link.'),
      '#default_value' => $this->configuration['text'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['text'] = $form_state->getValue('text');
    parent::submitConfigurationForm($form, $form_state);
  }

}
