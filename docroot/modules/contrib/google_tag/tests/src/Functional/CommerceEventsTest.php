<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\google_tag\Traits\CommerceStoreTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Commerce events through a guest shopping and checkout session.
 *
 * The kernel tests check each event's payload. This test checks that the
 * events reach the page they belong on across real form submissions and
 * redirects, including the events delayed until after a redirect.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class CommerceEventsTest extends BrowserTestBase {

  use CommerceStoreTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'google_tag',
    'commerce_product',
    'commerce_cart',
    'commerce_checkout',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // @todo Remove once #3499397 lands. Until then, Dynamic Page Cache serves
    //   the page after a redirect without the delayed add_to_cart event.
    $this->container->get('module_installer')->uninstall(['dynamic_page_cache']);
    $this->createCommerceFixtures();
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('view commerce_product')
      ->grantPermission('access checkout')
      ->save();
  }

  /**
   * Tests view_item, add_to_cart, begin_checkout and purchase.
   */
  public function testGuestCheckout(): void {
    $page = $this->getSession()->getPage();

    $this->drupalGet('product/1');
    $view_item = $this->getGoogleTagEvent('view_item');
    self::assertSame('USD', $view_item['currency']);
    $this->assertItem($view_item['items'][0], 'ABC123', 'FooBaz Widget - Blue');
    self::assertEquals(12, $view_item['value']);
    $this->assertNoGoogleTagEvent('add_to_cart');

    // add_to_cart is delayed until the page the form redirects to.
    $page->pressButton('Add to cart');
    $add_to_cart = $this->getGoogleTagEvent('add_to_cart');
    $this->assertItem($add_to_cart['items'][0], 'ABC123', 'FooBaz Widget - Blue');
    self::assertEquals(1, $add_to_cart['items'][0]['quantity']);
    self::assertEquals(12, $add_to_cart['value']);
    // A rebuilt page view must not send it again.
    $this->drupalGet('product/1');
    $this->assertNoGoogleTagEvent('add_to_cart');

    $this->drupalGet('cart');
    $page->pressButton('Checkout');
    $begin_checkout = $this->getGoogleTagEvent('begin_checkout');
    self::assertSame('USD', $begin_checkout['currency']);
    $this->assertItem($begin_checkout['items'][0], 'ABC123', 'FooBaz Widget - Blue');
    self::assertEquals(12, $begin_checkout['value']);

    $page->pressButton('Continue as Guest');
    $this->submitForm([
      'contact_information[email]' => 'baz@example.com',
      'contact_information[email_confirm]' => 'baz@example.com',
      'billing_information[profile][address][0][address][given_name]' => 'Baz',
      'billing_information[profile][address][0][address][family_name]' => 'Bar',
      'billing_information[profile][address][0][address][address_line1]' => '123 Main St',
      'billing_information[profile][address][0][address][locality]' => 'Kenosha',
      'billing_information[profile][address][0][address][administrative_area]' => 'WI',
      'billing_information[profile][address][0][address][postal_code]' => '53140',
    ], 'Continue to review');
    $this->assertNoGoogleTagEvent('purchase');
    $page->pressButton('Complete checkout');
    $this->assertSession()->pageTextContains('Your order number is 1.');

    $purchase = $this->getGoogleTagEvent('purchase');
    self::assertSame('USD', $purchase['currency']);
    self::assertEquals(1, $purchase['transaction_id']);
    $this->assertItem($purchase['items'][0], 'ABC123', 'FooBaz Widget - Blue');
    self::assertEquals(12, $purchase['value']);
  }

  /**
   * Returns the data of an event in the page's drupalSettings.gtag.events.
   *
   * @param string $name
   *   The event name.
   *
   * @return array
   *   The event data.
   */
  private function getGoogleTagEvent(string $name): array {
    foreach ($this->getDrupalSettings()['gtag']['events'] ?? [] as $event) {
      if ($event['name'] === $name) {
        return $event['data'];
      }
    }
    self::fail("The page has no $name event.");
  }

  /**
   * Asserts that the page has no event with the given name.
   *
   * @param string $name
   *   The event name.
   */
  private function assertNoGoogleTagEvent(string $name): void {
    $names = array_column($this->getDrupalSettings()['gtag']['events'] ?? [], 'name');
    self::assertNotContains($name, $names);
  }

  /**
   * Asserts the identifying fields of an event item.
   *
   * @param array $item
   *   The item.
   * @param string $sku
   *   The expected item_id.
   * @param string $title
   *   The expected item_name.
   */
  private function assertItem(array $item, string $sku, string $title): void {
    self::assertSame($sku, $item['item_id']);
    self::assertSame($title, $item['item_name']);
    self::assertSame('FooBar Store', $item['affiliation']);
  }

}
