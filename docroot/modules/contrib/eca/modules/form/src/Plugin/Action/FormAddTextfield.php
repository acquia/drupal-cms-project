<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Add a text field to a form.
 */
#[Action(
  id: 'eca_form_add_textfield',
  label: new TranslatableMarkup('Form: add text field'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Add a plain text field, textarea or formatted text to the current form in scope.'),
  version_introduced: '1.0.0',
)]
class FormAddTextfield extends FormAddFieldActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'type' => 'textfield',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTypeOptions(): array {
    $type_options = [
      'textfield' => $this->t('Textfield'),
      'textarea' => $this->t('Textarea'),
    ];
    if ($this->moduleHandler->moduleExists('filter')) {
      $type_options['text_format'] = $this->t('Formatted text');
    }
    return $type_options;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if ($this->configuration['type'] === 'text_format') {
      $dependencies['module'][] = 'filter';
    }
    return $dependencies;
  }

}
