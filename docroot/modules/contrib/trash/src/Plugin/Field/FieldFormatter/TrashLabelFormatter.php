<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'trash_label' formatter.
 *
 * Adds 'in_trash' query parameter to entity links so that deleted entities
 * can be viewed from the trash listing.
 */
#[FieldFormatter(
  id: 'trash_label',
  label: new TranslatableMarkup('Trash Label'),
  field_types: [
    'string',
    'uri',
  ],
)]
class TrashLabelFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();
    $options['show_entity_id'] = TRUE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['show_entity_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the entity ID.'),
      '#description' => $this->t('Include the entity ID as part of the label.'),
      '#default_value' => $this->getSetting('show_entity_id'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('show_entity_id')) {
      $summary[] = $this->t('Show the entity ID.');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    $value = parent::viewValue($item);

    if ($this->getSetting('show_entity_id')) {
      // @phpstan-ignore-next-line property.notFound
      $value['#context']['value'] = $item->value . ' (' . $item->getEntity()->id() . ')';
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityUrl(EntityInterface $entity) {
    $url = parent::getEntityUrl($entity);

    // Add in_trash query parameter for deleted entities.
    $query = $url->getOption('query') ?? [];
    $query['in_trash'] = '1';
    $url->setOption('query', $query);

    return $url;
  }

}
