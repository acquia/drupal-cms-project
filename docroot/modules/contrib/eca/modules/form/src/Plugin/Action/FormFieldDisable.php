<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Set a form field as disabled.
 */
#[Action(
  id: 'eca_form_field_disable',
  label: new TranslatableMarkup('Form field: set as disabled'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Disable a form field.'),
  version_introduced: '1.0.0',
)]
class FormFieldDisable extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup {
    return $human_readable ? $this->t('disabled') : 'disabled';
  }

  /**
   * {@inheritdoc}
   */
  protected function flagAllChildren(&$element, bool $flag): void {
    parent::flagAllChildren($element, $flag);
    if (empty($element['#input'])) {
      return;
    }
    if (!empty($element['#allow_focus'])) {
      if ($flag) {
        $element['#attributes']['readonly'] = 'readonly';
      }
      else {
        unset($element['#attributes']['readonly']);
      }
    }
    else {
      if ($flag) {
        $element['#attributes']['disabled'] = 'disabled';
      }
      else {
        unset($element['#attributes']['disabled']);
      }
    }
  }

}
