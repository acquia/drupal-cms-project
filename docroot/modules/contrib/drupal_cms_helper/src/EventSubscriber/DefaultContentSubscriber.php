<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\DefaultContent\ExportMetadata;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\Field\FieldItemInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjusts exported default content for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class DefaultContentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreExportEvent::class => 'preExport',
    ];
  }

  /**
   * Prepares to export a content entity.
   */
  public function preExport(PreExportEvent $event): void {
    $callbacks = $event->getCallbacks();
    $original = $callbacks['field_item:entity_reference'];

    // @todo Remove when https://www.drupal.org/i/3577579 is fixed or released.
    $callback = function (FieldItemInterface $item, ExportMetadata $metadata) use ($original): ?array {
      $target_type = $item->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getSetting('target_type');

      if ($target_type === 'taxonomy_term' && strval($item->target_id) === '0') {
        return NULL;
      }
      return $original($item, $metadata);
    };
    $event->setCallback('field_item:entity_reference', $callback);
  }

}
