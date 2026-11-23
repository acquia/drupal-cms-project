<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Events;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Url;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\google_tag\EventSubscriber\CommerceOrderSubscriber;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\Tests\google_tag\Kernel\AssertGoogleTagTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Commerce checkout event test.
 *
 * @requires module commerce_checkout
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class CommerceCheckoutEventsTest extends OrderKernelTestBase {

  use AssertGoogleTagTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'google_tag',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('commerce_checkout');
  }

  /**
   * Tests checkout begin.
   */
  public function testBeginCheckout(): void {
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XXXXXX'],
      'events' => [
        'commerce_begin_checkout' => [],
      ],
    ])->save();

    $user = $this->createUser();
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart(
      'default',
      $this->store,
      $user
    );
    $this->container->get('commerce_cart.cart_manager')->addOrderItem(
      $cart,
      OrderItem::create([
        'title' => 'T-shirt',
        'type' => 'default',
        'quantity' => 2,
        'unit_price' => new Price('12.00', 'USD'),
      ])
    );
    $this->container->get('current_user')->setAccount($user);

    $request = Request::create(
      Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $cart->id(),
      ])->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    $response = $this->doRequest(
      Request::create($response->headers->get('Location'))
    );
    self::assertEquals(200, $response->getStatusCode());
    $this->assertGoogleTagEvents([
      [
        'name' => 'begin_checkout',
        'data' => [
          'currency' => 'USD',
          'value' => '24',
          'items' => [
            [
              'item_id' => '',
              'item_name' => 'T-shirt',
              'affiliation' => 'Default store',
              'discount' => '0',
              'price' => '12',
              'quantity' => '2',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that an Ajax rebuild of the first step does not resend the event.
   */
  public function testBeginCheckoutAjaxRebuild(): void {
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => [
        'commerce_begin_checkout' => [],
      ],
    ])->save();
    // Two gateways, so the payment method radios are shown with #ajax.
    foreach (['cash_on_delivery', 'bank_transfer'] as $gateway_id) {
      PaymentGateway::create([
        'id' => $gateway_id,
        'label' => $gateway_id,
        'plugin' => 'manual',
        'configuration' => [
          'display_label' => $gateway_id,
          'collect_billing_information' => FALSE,
        ],
      ])->save();
    }

    $user = $this->createUser();
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart(
      'default',
      $this->store,
      $user
    );
    $this->container->get('commerce_cart.cart_manager')->addOrderItem(
      $cart,
      OrderItem::create([
        'title' => 'T-shirt',
        'type' => 'default',
        'quantity' => 2,
        'unit_price' => new Price('12.00', 'USD'),
      ])
    );
    $this->container->get('current_user')->setAccount($user);

    $uri = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $cart->id(),
      'step' => 'order_information',
    ])->toString();
    $this->doRequest(Request::create($uri));
    $events = array_column($this->getDrupalSettings()['gtag']['events'] ?? [], 'name');
    self::assertSame(['begin_checkout'], $events);

    // Switching the payment method rebuilds the first step over Ajax. The
    // page render above drained the collector, so any event command in this
    // response comes from the rebuild.
    $form_data = [
      'payment_information' => [
        'payment_method' => 'bank_transfer',
      ],
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      '_triggering_element_name' => 'payment_information[payment_method]',
      '_triggering_element_value' => 'bank_transfer',
    ];
    $response = $this->doRequest(Request::create(
      $uri . '?ajax_form=1&_wrapper_format=drupal_ajax',
      'POST',
      $form_data
    ));
    self::assertInstanceOf(AjaxResponse::class, $response);
    $commands = $response->getCommands();
    self::assertContains('insert', array_column($commands, 'command'));
    self::assertNotContains('gtagEvent', array_column($commands, 'command'));
  }

  /**
   * Tests purchase is sent once, on the first complete step render.
   */
  public function testPurchase(): void {
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XXXXXX'],
      'events' => [
        'commerce_purchase' => [],
      ],
    ])->save();

    $user = $this->createUser();
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart(
      'default',
      $this->store,
      $user
    );
    $this->container->get('commerce_cart.cart_manager')->addOrderItem(
      $cart,
      OrderItem::create([
        'title' => 'T-shirt',
        'type' => 'default',
        'quantity' => 2,
        'unit_price' => new Price('12.00', 'USD'),
      ])
    );
    // Place the order outside the customer's session, as a payment gateway
    // webhook does.
    $cart->getState()->applyTransitionById('place');
    $cart->save();
    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order = $order_storage->loadUnchanged($cart->id());
    self::assertTrue($order->getData(CommerceOrderSubscriber::PURCHASE_PENDING));

    $this->container->get('current_user')->setAccount($user);
    $url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $cart->id(),
    ])->toString();
    $response = $this->doRequest(Request::create($url));
    self::assertEquals(302, $response->getStatusCode());
    $complete_url = $response->headers->get('Location');
    $response = $this->doRequest(Request::create($complete_url));
    self::assertEquals(200, $response->getStatusCode());
    $this->assertGoogleTagEvents([
      [
        'name' => 'purchase',
        'data' => [
          'currency' => 'USD',
          'value' => '24',
          'transaction_id' => $cart->getOrderNumber(),
          'shipping' => '0',
          'tax' => '0',
          'items' => [
            [
              'item_id' => '',
              'item_name' => 'T-shirt',
              'affiliation' => 'Default store',
              'discount' => '0',
              'price' => '12',
              'quantity' => '2',
            ],
          ],
        ],
      ],
    ]);
    $order = $order_storage->loadUnchanged($cart->id());
    self::assertNull($order->getData(CommerceOrderSubscriber::PURCHASE_PENDING));

    // Refreshing the complete page must not send the purchase again.
    $this->container->set('google_tag.event_collector', NULL);
    $response = $this->doRequest(Request::create($complete_url));
    self::assertEquals(200, $response->getStatusCode());
    self::assertSame([], $this->drupalSettings['gtag']['events']);
  }

  /**
   * Sends request and sets raw content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Symfony request.
   *
   * @throws \Exception
   */
  protected function doRequest(Request $request): Response {
    $response = $this->container->get('http_kernel')->handle($request);
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);
    return $response;
  }

  /**
   * Tests orders placed before the flag existed send no purchase.
   */
  public function testPurchaseWithoutPendingFlag(): void {
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => [
        'commerce_purchase' => [],
      ],
    ])->save();

    $user = $this->createUser();
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart(
      'default',
      $this->store,
      $user
    );
    $this->container->get('commerce_cart.cart_manager')->addOrderItem(
      $cart,
      OrderItem::create([
        'title' => 'T-shirt',
        'type' => 'default',
        'quantity' => 1,
        'unit_price' => new Price('12.00', 'USD'),
      ])
    );
    $cart->getState()->applyTransitionById('place');
    $cart->save();
    $cart->unsetData(CommerceOrderSubscriber::PURCHASE_PENDING);
    $cart->save();

    $this->container->get('current_user')->setAccount($user);
    $response = $this->doRequest(Request::create(
      Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $cart->id(),
        'step' => 'complete',
      ])->toString()
    ));
    self::assertEquals(200, $response->getStatusCode());
    self::assertSame([], $this->drupalSettings['gtag']['events']);
  }

}
