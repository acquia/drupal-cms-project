<?php

declare(strict_types=1);

namespace Drupal\custom_field_search_api\EventSubscriber;

use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to events from the `search_api` module.
 */
class SearchApiEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [SearchApiEvents::MAPPING_FIELD_TYPES => 'onMappingFieldTypes'];
  }

  /**
   * Handle the `search_api.mapping_field_types` event.
   */
  public function onMappingFieldTypes(MappingFieldTypesEvent $event): void {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['custom_field_string_long'] = 'text';
  }

}
