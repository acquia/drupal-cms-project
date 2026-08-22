<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Alter a link element by adding an query argument.
 */
#[Action(
  id: 'eca_render_alter_link_add_query_argument',
  label: new TranslatableMarkup('Render: alter link, add query argument'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by adding a query argument.'),
  version_introduced: '3.0.3',
)]
class AlterLinkAddQuery extends AlterLinkAddAttribute {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $this->event->addQuery(
      $this->tokenService->replaceClear($this->configuration['key']),
      $this->tokenService->replaceClear($this->configuration['value']),
      $this->configuration['reset'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['key']['#title'] = $this->t('Name of query argument');
    $form['value']['#title'] = $this->t('Value of query argument');
    $form['reset']['#title'] = $this->t('Reset query arguments');
    $form['key']['#description'] = $this->t('The name of the query argument to add to the link. Leave empty to not add a query argument when the query arguments will be reset.');
    $form['value']['#description'] = $this->t('The value of the query argument to add to the link. Leave empty to not add a query argument when the query arguments will be reset.');
    $form['reset']['#description'] = $this->t('If checked, the currently set query arguments will be removed before adding the new query argument.');
    return $form;
  }

}
