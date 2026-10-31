<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by setting it absolute or not.
 */
#[Action(
  id: 'eca_render_alter_link_set_absolute',
  label: new TranslatableMarkup('Render: alter link, set absolute'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by setting it absolute or not.'),
  version_introduced: '3.0.3',
)]
class AlterLinkSetAbsolute extends AlterLinkBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->setAbsolute($this->configuration['absolute']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'absolute' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set absolute'),
      '#description' => $this->t('If checked, the link will be absolute. If not checked, the link will be relative.'),
      '#default_value' => $this->configuration['absolute'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['absolute'] = $form_state->getValue('absolute');
    parent::submitConfigurationForm($form, $form_state);
  }

}
