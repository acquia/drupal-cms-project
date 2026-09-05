<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;

/**
 * Plugin implementation of the 'file_url_plain' formatter.
 */
#[FieldFormatter(
  id: 'file_url_plain',
  label: new TranslatableMarkup('URL to file'),
  field_types: [
    'file',
    'image',
  ],
)]
class UrlPlainFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {

    if (!$value instanceof FileInterface) {
      return NULL;
    }

    /** @var \Drupal\Core\Access\AccessResultInterface $access */
    $access = $this->checkAccess($value);
    if (!$access->isAllowed()) {
      return NULL;
    }

    return [
      '#markup' => $value->createFileUrl(),
      '#cache' => [
        'tags' => $value->getCacheTags(),
      ],
    ];
  }

}
