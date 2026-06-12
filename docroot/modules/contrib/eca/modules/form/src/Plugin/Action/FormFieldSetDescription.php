<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Set description on a field or form element.
 */
#[Action(
  id: 'eca_form_field_set_description',
  label: new TranslatableMarkup('Form field: set description'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Defines description on a form field or element. Leave empty to unset.'),
  version_introduced: '3.1.1',
)]
class FormFieldSetDescription extends FormFieldActionBase {

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    if ($element = &$this->getTargetElement()) {
      $element = &$this->jumpToFirstFieldChild($element);
      if ($element) {
        $description = trim((string) $this->tokenService->replaceClear($this->configuration['description']));
        if ($description !== '') {
          $element['#description'] = $description;
        }
        else {
          unset($element['#description']);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'description' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Element description'),
      '#description' => $this->t('The description of the form field or element.'),
      '#default_value' => $this->configuration['description'],
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['description'] = $form_state->getValue('description');
    parent::submitConfigurationForm($form, $form_state);
  }

}
