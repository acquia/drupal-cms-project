<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Trait\EnumTrait;
use Drupal\custom_field\Plugin\PropWidgetBase;

/**
 * Plugin implementation of the 'string' widget.
 */
#[PropWidget(
  id: 'string',
  prop_type: 'string',
  label: new TranslatableMarkup('String'),
)]
class PropWidgetString extends PropWidgetBase {

  use EnumTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'maxlength' => '',
      'pattern' => '',
      'enum' => [],
      'meta:enum' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $settings = $this->getSettings() + static::defaultSettings();
    $enum = $settings['enum'];
    $element['value']['#type'] = 'textfield';
    if (!empty($enum)) {
      $element['value']['#type'] = 'select';
      $element['value']['#options'] = $this->getEnumOptions($settings);
      $element['value']['#empty_option'] = $this->t('- None -');
    }
    else {
      if (is_int($settings['maxlength'])) {
        $element['value']['#maxlength'] = $settings['maxlength'];
      }
      if (!empty($settings['pattern'])) {
        $element['value']['#pattern'] = $settings['pattern'];
      }
    }

    return $element;
  }

}
