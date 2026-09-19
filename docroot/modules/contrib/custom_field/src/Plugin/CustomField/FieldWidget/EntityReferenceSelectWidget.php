<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'entity_reference_select' widget.
 */
#[CustomFieldWidget(
  id: 'entity_reference_select',
  label: new TranslatableMarkup('Select'),
  category: new TranslatableMarkup('Reference'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceSelectWidget extends EntityReferenceOptionsWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element += [
      '#empty_option' => $settings['empty_option'],
    ];

    return ['target_id' => $element];
  }

}
