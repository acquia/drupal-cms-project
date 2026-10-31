<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldType\TelephoneType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'telephone' widget.
 */
#[CustomFieldWidget(
  id: 'telephone',
  label: new TranslatableMarkup('Telephone'),
  category: new TranslatableMarkup('General'),
  field_types: [
    'telephone',
  ],
)]
class TelephoneWidget extends TextWidget {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof TelephoneType);
    $field_settings = $field->getFieldSettings();
    $element['#type'] = 'tel';
    $element['#maxlength'] = TelephoneType::MAX_LENGTH;
    if (!empty($field_settings['pattern'])) {
      $format = $field->getTelephoneFormats()[$field_settings['pattern']];
      $element['#attributes']['pattern'] = $format['pattern'];
      $element['#description'] = $field_settings['description'] ?: $this->t('Enter a telephone number in the format: %format', ['%format' => $format['format']]);
    }

    return $element;
  }

}
