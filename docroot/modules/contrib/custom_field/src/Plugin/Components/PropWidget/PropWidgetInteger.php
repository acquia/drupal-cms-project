<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\custom_field\Trait\EnumTrait;

/**
 * Plugin implementation of the 'integer' widget.
 */
#[PropWidget(
  id: 'integer',
  prop_type: 'integer',
  label: new TranslatableMarkup('Integer'),
)]
class PropWidgetInteger extends PropWidgetBase {

  use EnumTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'minimum' => '',
      'maximum' => '',
      'enum' => [],
      'meta:enum' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value = NULL, $required = FALSE): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $settings = $this->getSettings() + static::defaultSettings();
    $enum = $settings['enum'];
    $element['value']['#type'] = 'number';
    if (!empty($enum)) {
      $element['value']['#type'] = 'select';
      $element['value']['#options'] = $this->getEnumOptions($settings);
      $element['value']['#empty_option'] = $this->t('- None -');
    }
    else {
      if (is_int($settings['minimum'])) {
        $element['value']['#min'] = $settings['minimum'];
      }
      if (is_int($settings['maximum'])) {
        $element['value']['#max'] = $settings['maximum'];
      }
    }

    return $element;
  }

}
