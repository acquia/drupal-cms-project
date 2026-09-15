<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'telephone_link' formatter.
 */
#[FieldFormatter(
  id: 'telephone_link',
  label: new TranslatableMarkup('Telephone link'),
  field_types: [
    'telephone',
  ],
)]
class TelephoneLinkFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'title' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Title to replace basic numeric telephone number display'),
      '#default_value' => $this->getSetting('title'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): array {
    // If the telephone number is 5 or less digits, parse_url() will think
    // it's a port number rather than a phone number which causes the link
    // formatter to throw an InvalidArgumentException. Avoid this by inserting
    // a dash (-) after the first digit - RFC 3966 defines the dash as a
    // visual separator character and so will be removed before the phone
    // number is used. See https://bugs.php.net/bug.php?id=70588 for more.
    // While the bug states this only applies to numbers <= 65535, a 5 digit
    // number greater than 65535 will cause parse_url() to return FALSE so
    // we need the work around on any 5 digit (or less) number.
    // First we strip whitespace so we're counting actual digits.
    $phone_number = (string) preg_replace('/\s+/', '', $value);
    if (strlen($phone_number) <= 5) {
      $phone_number = (string) substr_replace($phone_number, '-', 1, 0);
    }

    return [
      '#type' => 'link',
      '#title' => !empty($this->getSetting('title')) ? $this->getSetting('title') : $value,
      '#url' => Url::fromUri('tel:' . rawurlencode($phone_number)),
      '#options' => ['external' => TRUE],
    ];
  }

}
