<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'link_text' formatter.
 */
#[FieldFormatter(
  id: 'link_text',
  label: new TranslatableMarkup('Link text'),
  field_types: [
    'link',
  ],
)]
class LinkTextFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link_text' => '',
      'trim_length' => '80',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('This field can serve as the fallback link text when title is unavailable.'),
      '#default_value' => $this->getSetting('link_text'),
      '#placeholder' => $this->t('e.g. Learn More'),
    ];
    $elements['trim_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Trim link text length'),
      '#field_suffix' => $this->t('characters'),
      '#default_value' => $this->getSetting('trim_length'),
      '#min' => 1,
      '#description' => $this->t('Leave blank to allow unlimited link text lengths.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    $settings = $this->getSettings();
    $link_title = !empty($value['title']) ? $value['title'] : $settings['link_text'];

    if (empty($link_title)) {
      return NULL;
    }

    // Trim the link text to the desired length.
    if (!empty($settings['trim_length'])) {
      $link_title = Unicode::truncate($link_title, $settings['trim_length'], FALSE, TRUE);
    }

    return [
      '#plain_text' => $link_title,
    ];
  }

}
