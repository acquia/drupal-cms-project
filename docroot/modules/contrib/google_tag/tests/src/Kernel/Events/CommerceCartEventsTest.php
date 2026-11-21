<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Events;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\commerce_cart\Kernel\CartKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Commerce carts add/removal events test.
 *
 * @requires module commerce_cart
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class CommerceCartEventsTest extends CartKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_price_test', 'google_tag'];

  /**
   * Tests cart removal/addition events.
   */
  public function testEvents(): void {
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'events' => [
        'commerce_add_to_cart' => [],
        'commerce_remove_from_cart' => [],
      ],
    ])->save();

    $product = Product::create([
      'type' => 'default',
      'title' => $this->randomString(),
    ]);
    $product->save();

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'product_id' => $product->id(),
      'price' => new Price('12.00', 'USD'),
      'status' => 1,
    ]);
    $variation->save();

    $cart = $this->cartProvider->createCart('default', $this->store);
    $order_item = $this->cartManager->addEntity($cart, $variation);

    $events = $this->container->get('google_tag.event_collector')->getEvents();
    self::assertCount(1, $events);
    self::assertEquals('add_to_cart', $events[0]->getName());
    self::assertEquals([
      'currency' => 'USD',
      'value' => '12.00',
      'items' => [
        [
          'item_name' => $variation->getOrderItemTitle(),
          'affiliation' => $this->store->label(),
          'discount' => '0',
          'price' => '12.00',
          'quantity' => '1',
          'item_id' => $variation->getSku(),
        ],
      ],
    ], $events[0]->getData());

    $this->cartManager->removeOrderItem($cart, $order_item);

    $events = $this->container->get('google_tag.event_collector')->getEvents();
    self::assertCount(1, $events);
    self::assertEquals('remove_from_cart', $events[0]->getName());
    self::assertEquals([
      'currency' => 'USD',
      'value' => '12.00',
      'items' => [
        [
          'item_name' => $variation->getOrderItemTitle(),
          'affiliation' => $this->store->label(),
          'discount' => '0',
          'price' => '12.00',
          'quantity' => '1',
          'item_id' => $variation->getSku(),
        ],
      ],
    ], $events[0]->getData());
  }

  /**
   * Tests delayed cart events rendered after the cart was deleted.
   */
  public function testEventsAfterCartDeleted(): void {
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'events' => [
        'commerce_add_to_cart' => [],
        'commerce_remove_from_cart' => [],
      ],
    ])->save();

    $product = Product::create([
      'type' => 'default',
      'title' => $this->randomString(),
    ]);
    $product->save();

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'product_id' => $product->id(),
      'price' => new Price('12.00', 'USD'),
      'status' => 1,
    ]);
    $variation->save();

    $cart = $this->cartProvider->createCart('default', $this->store);
    $order_item = $this->cartManager->addEntity($cart, $variation);
    $this->cartManager->removeOrderItem($cart, $order_item);

    // Delayed events are serialized into the session and rendered on a later
    // request, by which time the cart may be gone.
    $events = $this->container->get('google_tag.event_collector')->getEvents();
    // The session stores whole entities, so allowed classes can't be limited.
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize
    $events = unserialize(serialize($events));
    $cart->delete();

    self::assertCount(2, $events);
    $expected_item = [
      'item_name' => $variation->getOrderItemTitle(),
      'discount' => '0',
      'price' => '12.00',
      'quantity' => '1',
      'item_id' => $variation->getSku(),
    ];
    self::assertEquals('add_to_cart', $events[0]->getName());
    self::assertEquals([
      'currency' => 'USD',
      'value' => '12.00',
      'items' => [$expected_item],
    ], $events[0]->getData());
    self::assertEquals('remove_from_cart', $events[1]->getName());
    self::assertEquals([
      'currency' => 'USD',
      'value' => '12.00',
      'items' => [$expected_item],
    ], $events[1]->getData());
  }

  /**
   * Tests cart events for an order item whose price is not resolved yet.
   *
   * Commerce resolves the unit price when the cart is refreshed on save, which
   * happens after the cart events are dispatched, or not at all when the cart
   * is not saved.
   */
  public function testEventsWithoutUnitPrice(): void {
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'events' => [
        'commerce_add_to_cart' => [],
        'commerce_remove_from_cart' => [],
      ],
    ])->save();

    $product = Product::create([
      'type' => 'default',
      'title' => $this->randomString(),
    ]);
    $product->save();

    // Commerce's test resolver takes 3.00 off variations with a TEST_ SKU, so
    // the resolved price differs from the variation's base price.
    $priced_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_' . strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'product_id' => $product->id(),
      'price' => new Price('12.00', 'USD'),
      'status' => 1,
    ]);
    $priced_variation->save();
    $unpriced_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'product_id' => $product->id(),
      'status' => 1,
    ]);
    $unpriced_variation->save();

    $collector = $this->container->get('google_tag.event_collector');
    $cart = $this->cartProvider->createCart('default', $this->store);

    // The resolved price is used until the cart refresh prices the item.
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $priced_variation->id(),
      'quantity' => 1,
    ]);
    $this->cartManager->addOrderItem($cart, $order_item, TRUE, FALSE);
    self::assertNull($order_item->getUnitPrice());
    $events = $collector->getEvents();
    $event = end($events);
    self::assertEquals('add_to_cart', $event->getName());
    self::assertEquals([
      'currency' => 'USD',
      'value' => '9.00',
      'items' => [
        [
          'item_name' => $order_item->label(),
          'affiliation' => $this->store->label(),
          'discount' => '0',
          'price' => '9.00',
          'quantity' => 1,
          'item_id' => $priced_variation->getSku(),
        ],
      ],
    ], $event->getData());

    $this->cartManager->removeOrderItem($cart, $order_item, FALSE);
    $events = $collector->getEvents();
    $event = end($events);
    self::assertEquals('remove_from_cart', $event->getName());
    self::assertEquals('9.00', $event->getData()['value']);

    // Without any price, the price parameters are left out.
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $unpriced_variation->id(),
      'quantity' => 1,
    ]);
    $this->cartManager->addOrderItem($cart, $order_item, TRUE, FALSE);
    $expected = [
      'items' => [
        [
          'item_name' => $order_item->label(),
          'affiliation' => $this->store->label(),
          'quantity' => 1,
          'item_id' => $unpriced_variation->getSku(),
        ],
      ],
    ];
    $events = $collector->getEvents();
    $event = end($events);
    self::assertEquals('add_to_cart', $event->getName());
    self::assertSame($expected, $event->getData());

    $this->cartManager->removeOrderItem($cart, $order_item, FALSE);
    $events = $collector->getEvents();
    $event = end($events);
    self::assertEquals('remove_from_cart', $event->getName());
    self::assertSame($expected, $event->getData());
  }

}
