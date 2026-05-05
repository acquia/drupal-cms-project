<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetBase;

/**
 * Plugin implementation of the 'boolean' widget.
 */
#[PropWidget(
  id: 'boolean',
  prop_type: 'boolean',
  label: new TranslatableMarkup('Boolean'),
)]
class PropWidgetBoolean extends PropWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $property, mixed $value): array {
    return [
      $this->t('@space@property: @value', [
        '@space' => $this->space(),
        '@property' => $property,
        '@value' => $value ? self::BOOLEAN_TRUE : self::BOOLEAN_FALSE,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $element['value'] = [
      '#type' => 'checkbox',
      '#default_value' => !empty($value['value']),
    ] + $element['value'];

    return $element;
  }

}
