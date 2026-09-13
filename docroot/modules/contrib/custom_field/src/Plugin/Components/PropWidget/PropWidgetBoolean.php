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
  public function settingsSummary(string $property, mixed $value, int $indent): array {
    return [
      $this->t('@space@property: @value', [
        '@space' => $this->space(),
        '@property' => $property,
        '@value' => !empty($value) ? self::BOOLEAN_TRUE : self::BOOLEAN_FALSE,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required, array $context = []): array {
    $element = parent::widget($form, $form_state, $value, $required, $context);
    $element['value'] = [
      '#type' => 'checkbox',
      '#default_value' => !empty($value['value']),
    ] + $element['value'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    $value['value'] = !empty($value['value']);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(mixed $value, array $context = []): ?bool {
    if (!is_bool($value) && !is_numeric($value) && !is_null($value)) {
      return NULL;
    }

    return (bool) $value;
  }

}
