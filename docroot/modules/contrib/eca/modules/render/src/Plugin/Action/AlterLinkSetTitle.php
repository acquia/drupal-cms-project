<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by setting a title attribute.
 */
#[Action(
  id: 'eca_render_alter_link_set_title',
  label: new TranslatableMarkup('Render: alter link, set title attribute'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by setting its title attribute.'),
  version_introduced: '3.0.3',
)]
class AlterLinkSetTitle extends AlterLinkBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->setTitle($this->tokenService->replaceClear($this->configuration['title']));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'title' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title attribute'),
      '#description' => $this->t('The title attribute. Leave empty to unset the title attribute.'),
      '#default_value' => $this->configuration['title'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['title'] = $form_state->getValue('title');
    parent::submitConfigurationForm($form, $form_state);
  }

}
