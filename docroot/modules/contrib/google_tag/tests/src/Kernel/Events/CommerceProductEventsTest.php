<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Events;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Session\AccountInterface;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\Tests\google_tag\Kernel\AssertGoogleTagTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Commerce product events test.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class CommerceProductEventsTest extends CommerceKernelTestBase {

  use AssertGoogleTagTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'commerce_product',
    'google_tag',
    'google_tag_test',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['user', 'commerce_product']);
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    Role::load(AccountInterface::ANONYMOUS_ROLE)->grantPermission('view commerce_product')->save();
  }

  /**
   * Test view item list event.
   */
  public function testViewItemList(): void {
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XXXXXX'],
      'events' => [
        'commerce_view_item_list' => [],
      ],
    ])->save();
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'price' => new Price('12.00', 'USD'),
      'status' => 1,
    ]);
    $variation->save();
    $product = Product::create([
      'type' => 'default',
      'title' => 'FooBaz Widget',
      'stores' => [$this->store],
      'variations' => [$variation],
      'status' => 1,
    ]);
    $product->save();

    $request = Request::create('/catalog');
    $response = $this->container->get('http_kernel')->handle($request);
    self::assertEquals(200, $response->getStatusCode());
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    $this->assertGoogleTagEvents([
      [
        'name' => 'view_item_list',
        'data' => [
          'item_list_id' => 'catalog',
          'item_list_name' => 'Catalog',
          'items' => [
            [
              'item_name' => $variation->label(),
              'item_id' => $variation->getSku(),
              'affiliation' => $this->store->label(),
              'price' => '12.00',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests the view_item event for a variation without a price.
   */
  public function testViewItemWithoutPrice(): void {
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => [
        'commerce_view_item' => [],
      ],
    ])->save();
    $variation = $this->createUnpricedVariation();
    $product = Product::create([
      'type' => 'default',
      'title' => 'FooBaz Widget',
      'stores' => [$this->store],
      'variations' => [$variation],
      'status' => 1,
    ]);
    $product->save();

    $request = Request::create($product->toUrl()->toString());
    $response = $this->container->get('http_kernel')->handle($request);
    self::assertEquals(200, $response->getStatusCode());
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    $this->assertGoogleTagEvents([
      [
        'name' => 'view_item',
        'data' => [
          'items' => [
            [
              'item_name' => $variation->label(),
              'item_id' => $variation->getSku(),
              'affiliation' => $this->store->label(),
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests the view_item_list event for a variation without a price.
   */
  public function testViewItemListWithoutPrice(): void {
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => [
        'commerce_view_item_list' => [],
      ],
    ])->save();
    $variation = $this->createUnpricedVariation();
    Product::create([
      'type' => 'default',
      'title' => 'FooBaz Widget',
      'stores' => [$this->store],
      'variations' => [$variation],
      'status' => 1,
    ])->save();

    $request = Request::create('/catalog');
    $response = $this->container->get('http_kernel')->handle($request);
    self::assertEquals(200, $response->getStatusCode());
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    $this->assertGoogleTagEvents([
      [
        'name' => 'view_item_list',
        'data' => [
          'item_list_id' => 'catalog',
          'item_list_name' => 'Catalog',
          'items' => [
            [
              'item_name' => $variation->label(),
              'item_id' => $variation->getSku(),
              'affiliation' => $this->store->label(),
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Creates a published product variation with no price.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariation
   *   The saved variation.
   */
  private function createUnpricedVariation(): ProductVariation {
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'status' => 1,
    ]);
    $variation->save();
    self::assertNull($variation->getPrice());
    return $variation;
  }

  /**
   * Tests that products without a default variation are skipped.
   */
  public function testViewItemListProductWithoutVariation(): void {
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => [
        'commerce_view_item_list' => [],
      ],
    ])->save();
    Product::create([
      'type' => 'default',
      'title' => 'No variations',
      'stores' => [$this->store],
      'variations' => [],
      'status' => 1,
    ])->save();
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'price' => new Price('12.00', 'USD'),
      'status' => 1,
    ]);
    $variation->save();
    Product::create([
      'type' => 'default',
      'title' => 'FooBaz Widget',
      'stores' => [$this->store],
      'variations' => [$variation],
      'status' => 1,
    ])->save();

    $request = Request::create('/catalog');
    $response = $this->container->get('http_kernel')->handle($request);
    self::assertEquals(200, $response->getStatusCode());
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    $this->assertGoogleTagEvents([
      [
        'name' => 'view_item_list',
        'data' => [
          'item_list_id' => 'catalog',
          'item_list_name' => 'Catalog',
          'items' => [
            [
              'item_name' => $variation->label(),
              'item_id' => $variation->getSku(),
              'affiliation' => $this->store->label(),
              'price' => '12.00',
            ],
          ],
        ],
      ],
    ]);
  }

}
