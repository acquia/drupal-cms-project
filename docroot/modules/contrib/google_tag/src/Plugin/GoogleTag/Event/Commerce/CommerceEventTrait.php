<?php

declare(strict_types=1);

namespace Drupal\google_tag\Plugin\GoogleTag\Event\Commerce;

use Drupal\commerce\Context;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;

/**
 * Price helpers for the Commerce events.
 */
trait CommerceEventTrait {

  /**
   * Formats price number.
   *
   * @param \Drupal\commerce_price\Price $price
   *   Price object.
   *
   * @return string
   *   Formatted price.
   */
  protected function formatPriceNumber(Price $price): string {
    return number_format((float) $price->getNumber(), 2, '.', '');
  }

  /**
   * Returns the unit price of an order item, resolving it when it is not set.
   *
   * Commerce sets the unit price when the cart is refreshed on save, which can
   * happen after a cart event was collected, or not at all when the cart is not
   * saved. The price is resolved the same way the refresh does, so price lists
   * and custom price resolvers are respected.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The unit price, or NULL when the item has no price.
   */
  protected function getOrderItemUnitPrice(OrderItemInterface $order_item): ?Price {
    $unit_price = $order_item->getUnitPrice();
    if ($unit_price !== NULL) {
      return $unit_price;
    }
    $purchased_entity = $order_item->getPurchasedEntity();
    $order = $order_item->getOrder();
    if ($purchased_entity === NULL || $order === NULL) {
      return NULL;
    }
    $context = new Context($order->getCustomer(), $order->getStore(), (int) $order->getCalculationDate()->format('U'));
    return \Drupal::service('commerce_price.chain_price_resolver')
      ->resolve($purchased_entity, $order_item->getQuantity(), $context);
  }

}
