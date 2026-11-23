<?php

declare(strict_types=1);

namespace Drupal\google_tag\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\google_tag\EventCollectorInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Commerce order placed subscriber.
 */
final class CommerceOrderSubscriber implements EventSubscriberInterface {

  /**
   * Order data key marking an order whose purchase event is not yet sent.
   */
  public const PURCHASE_PENDING = 'google_tag_purchase_pending';

  /**
   * The Event Collector.
   *
   * @var \Drupal\google_tag\EventCollectorInterface
   */
  private EventCollectorInterface $eventCollector;

  /**
   * CommerceOrderSubscriber constructor.
   *
   * @param \Drupal\google_tag\EventCollectorInterface $eventCollector
   *   Collector service.
   */
  public function __construct(EventCollectorInterface $eventCollector) {
    $this->eventCollector = $eventCollector;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.pre_transition' => 'onPlace',
    ];
  }

  /**
   * Flags a placed order so checkout completion sends its purchase event.
   *
   * The order is often placed in a request that redirects, or in a payment
   * gateway webhook with no customer session, so the event cannot be sent
   * here. The flag is saved with the order and consumed when the customer
   * first sees the checkout complete step.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   Event object.
   *
   * @see google_tag_form_commerce_checkout_flow_alter()
   */
  public function onPlace(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();
    if ($order instanceof OrderInterface) {
      $order->setData(self::PURCHASE_PENDING, TRUE);
    }
  }

  /**
   * Fires an event on successful paid order.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   Event object.
   *
   * @deprecated in google_tag:2.0.10 and is removed from google_tag:3.0.0. The
   *   purchase event is sent on checkout completion of a placed order.
   *
   * @see https://www.drupal.org/project/google_tag/issues/3437889
   */
  public function onPaid(OrderEvent $event): void {
    @trigger_error(__METHOD__ . '() is deprecated in google_tag:2.0.10 and is removed from google_tag:3.0.0. The purchase event is sent on checkout completion of a placed order. See https://www.drupal.org/project/google_tag/issues/3437889', E_USER_DEPRECATED);
    $this->eventCollector->addEvent('commerce_purchase', [
      'order' => $event->getOrder(),
    ]);
  }

}
