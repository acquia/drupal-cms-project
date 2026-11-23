<?php

declare(strict_types=1);

namespace Drupal\google_tag_attribute_test\Plugin\GoogleTag\Event;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\google_tag\Attribute\GoogleTagEvent;
use Drupal\google_tag\Plugin\GoogleTag\Event\EventBase;

/**
 * Attribute event plugin that depends on a module which is not installed.
 */
#[GoogleTagEvent(
  id: 'attribute_test_dependency',
  label: new TranslatableMarkup('Attribute test with dependency'),
  event_name: 'attribute_dependency',
  dependency: 'node',
)]
final class AttributeDependencyEvent extends EventBase {

}
