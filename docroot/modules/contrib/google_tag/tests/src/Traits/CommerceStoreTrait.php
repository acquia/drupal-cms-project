<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Traits;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\commerce_product\Entity\ProductAttributeValue;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\Store;
use Drupal\google_tag\Entity\TagContainer;

/**
 * Creates a store, a two-variation product and a container with its events.
 */
trait CommerceStoreTrait {

  /**
   * Creates the Commerce fixtures shared by the browser tests.
   *
   * The product has a blue (ABC123) and a red (DEF456) variation, both 12 USD,
   * selected by a color attribute on the add to cart form.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The product.
   */
  protected function createCommerceFixtures(): ProductInterface {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => ['G-XXXXXX'],
      'events' => [
        'commerce_add_to_cart' => [],
        'commerce_begin_checkout' => [],
        'commerce_purchase' => [],
        'commerce_remove_from_cart' => [],
        'commerce_view_item' => [],
      ],
    ])->save();

    $this->container->get('commerce_price.currency_importer')->import('USD');
    $store = Store::create([
      'type' => 'online',
      'uid' => 1,
      'name' => 'FooBar Store',
      'mail' => 'foo@example.com',
      'default_currency' => 'USD',
      'timezone' => 'America/Chicago',
      'address' => [
        'country_code' => 'US',
        'address_line1' => '1 Store St',
        'locality' => 'Madison',
        'administrative_area' => 'WI',
        'postal_code' => '53597',
      ],
      'billing_countries' => ['US'],
      'is_default' => TRUE,
    ]);
    $store->save();

    $attribute = ProductAttribute::create(['id' => 'color', 'label' => 'Color']);
    $attribute->save();
    $this->container->get('commerce_product.attribute_field_manager')->createField($attribute, 'default');

    $variations = [];
    foreach (['ABC123' => 'Blue', 'DEF456' => 'Red'] as $sku => $color) {
      $value = ProductAttributeValue::create(['attribute' => 'color', 'name' => $color]);
      $value->save();
      $variation = ProductVariation::create([
        'type' => 'default',
        'sku' => $sku,
        'price' => ['number' => '12.00', 'currency_code' => 'USD'],
        'attribute_color' => $value,
      ]);
      $variation->save();
      $variations[] = $variation;
    }

    $product = Product::create([
      'type' => 'default',
      'title' => 'FooBaz Widget',
      'stores' => [$store],
      'variations' => $variations,
    ]);
    $product->save();
    return $product;
  }

}
