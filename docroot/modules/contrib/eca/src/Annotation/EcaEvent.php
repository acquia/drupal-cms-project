<?php

namespace Drupal\eca\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines ECA event annotation object.
 *
 * @Annotation
 *
 * @deprecated in eca:3.0.0 and is removed from eca:4.0.0. Use attributes
 * instead.
 *
 * @see https://www.drupal.org/project/eca/issues/3456789
 */
class EcaEvent extends Plugin {

  /**
   * Label of the event.
   *
   * @var string
   */
  public string $label;

  /**
   * Name of the event being covered.
   *
   * @var string
   */
  public string $event_name;

  /**
   * Priority when subscribing to the covered event.
   *
   * @var int
   */
  public int $subscriber_priority;

  /**
   * Event class to which this ECA event subscribes.
   *
   * @var string
   */
  public string $event_class;

  /**
   * Tag for event characterization.
   *
   * @var int
   */
  public int $tags;

}
