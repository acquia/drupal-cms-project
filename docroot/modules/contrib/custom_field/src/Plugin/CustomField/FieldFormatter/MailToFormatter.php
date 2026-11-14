<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'email_mailto' formatter.
 */
#[FieldFormatter(
  id: 'email_mailto',
  label: new TranslatableMarkup('Email'),
  field_types: [
    'email',
  ],
)]
class MailToFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    // Check if email is valid.
    if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
      return NULL;
    }
    $email = Html::escape($value);
    return [
      '#markup' => '<a href="mailto:' . $email . '">' . $email . '</a>',
    ];
  }

}
