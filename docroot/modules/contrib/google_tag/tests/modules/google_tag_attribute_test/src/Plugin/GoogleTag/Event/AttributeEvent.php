<?php

declare(strict_types=1);

namespace Drupal\google_tag_attribute_test\Plugin\GoogleTag\Event;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\google_tag\Attribute\GoogleTagEvent;
use Drupal\google_tag\Plugin\GoogleTag\Event\EventBase;

/**
 * Event plugin defined with an attribute.
 */
#[GoogleTagEvent(
  id: 'attribute_test',
  label: new TranslatableMarkup('Attribute test'),
  description: new TranslatableMarkup('An event defined with an attribute.'),
)]
final class AttributeEvent extends EventBase {

}
