<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'file_default' formatter.
 */
#[FieldFormatter(
  id: 'file_default',
  label: new TranslatableMarkup('Generic file'),
  field_types: [
    'file',
    'image',
  ],
)]
class GenericFileFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {

    if (!$value instanceof EntityInterface) {
      return NULL;
    }

    /** @var \Drupal\Core\Access\AccessResultInterface $access */
    $access = $this->checkAccess($value);
    if (!$access->isAllowed()) {
      return NULL;
    }

    return [
      '#theme' => 'file_link',
      '#file' => $value,
      '#description' => NULL,
      '#cache' => [
        'tags' => $value->getCacheTags(),
      ],
    ];
  }

}
