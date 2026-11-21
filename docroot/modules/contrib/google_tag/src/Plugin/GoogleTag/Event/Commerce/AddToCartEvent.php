<?php

declare(strict_types=1);

namespace Drupal\google_tag\Plugin\GoogleTag\Event\Commerce;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\google_tag\Plugin\GoogleTag\Event\EventBase;

/**
 * Add to cart event.
 *
 * @GoogleTagEvent(
 *   id = "commerce_add_to_cart",
 *   event_name = "add_to_cart",
 *   label = @Translation("Add to cart"),
 *   description = @Translation("This event signifies that an item was added to a cart for purchase."),
 *   dependency = "commerce_cart",
 *   context_definitions = {
 *      "item" = @ContextDefinition("entity:commerce_order_item")
 *   }
 * )
 */
final class AddToCartEvent extends EventBase {

  use CommerceEventTrait;

  /**
   * {@inheritDoc}
   */
  public function getData(): array {
    // @todo leverage config for token replacement.
    $order_item = $this->getContextValue('item');
    assert($order_item instanceof OrderItemInterface);
    $purchased_entity = $order_item->getPurchasedEntity();
    $unit_price = $this->getOrderItemUnitPrice($order_item);
    // An item that is not priced yet has no adjustments either.
    $adjusted_price = $order_item->getUnitPrice() === NULL ? $unit_price : $order_item->getAdjustedUnitPrice();

    $item_data = [
      'item_name' => $order_item->label(),
      'affiliation' => $order_item->getOrder()?->getStore()?->label(),
    ];
    // Delayed events reload the order from storage when they are rendered, and
    // the cart may have been deleted by then.
    if ($item_data['affiliation'] === NULL) {
      unset($item_data['affiliation']);
    }
    if ($unit_price !== NULL) {
      $item_data['discount'] = $unit_price->subtract($adjusted_price)->getNumber();
      $item_data['price'] = $this->formatPriceNumber($unit_price);
    }
    $item_data['quantity'] = (int) $order_item->getQuantity();
    if ($purchased_entity instanceof ProductVariationInterface) {
      $item_data['item_id'] = $purchased_entity->getSku();
    }
    elseif ($purchased_entity !== NULL) {
      $item_data['item_id'] = $purchased_entity->id();
    }

    $data = [];
    if ($unit_price !== NULL) {
      $data['currency'] = $unit_price->getCurrencyCode();
      $data['value'] = $this->formatPriceNumber($adjusted_price);
    }
    $data['items'] = [$item_data];
    return $data;
  }

}
