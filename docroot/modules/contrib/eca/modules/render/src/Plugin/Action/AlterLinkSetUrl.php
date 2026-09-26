<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by setting its url.
 */
#[Action(
  id: 'eca_render_alter_link_set_url',
  label: new TranslatableMarkup('Render: alter link, set url'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by setting its url.'),
  version_introduced: '3.0.3',
)]
class AlterLinkSetUrl extends AlterLinkBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->setUrl(Url::fromUserInput($this->tokenService->replaceClear($this->configuration['url'])));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The url of the link.'),
      '#default_value' => $this->configuration['url'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['url'] = $form_state->getValue('url');
    parent::submitConfigurationForm($form, $form_state);
  }

}
