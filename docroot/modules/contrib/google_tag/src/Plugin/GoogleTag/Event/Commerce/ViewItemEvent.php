<?php

declare(strict_types=1);

namespace Drupal\google_tag\Plugin\GoogleTag\Event\Commerce;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\google_tag\Plugin\GoogleTag\Event\EventBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View item event plugin.
 *
 * @GoogleTagEvent(
 *   id = "commerce_view_item",
 *   event_name = "view_item",
 *   label = @Translation("View item"),
 *   description = @Translation("This event signifies that some content was shown to the user. Use this event to discover the most popular items viewed."),
 *   dependency = "commerce_product",
 *   context_definitions = {
 *      "item" = @ContextDefinition("entity:commerce_product_variation")
 *   }
 * )
 *
 * @todo allow support of generic purchasable entity types.
 */
final class ViewItemEvent extends EventBase implements ContainerFactoryPluginInterface, CacheableDependencyInterface {

  use CommerceEventTrait;

  /**
   * Current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected CurrentStoreInterface $currentStore;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->currentStore = $container->get('commerce_store.current_store');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getData(): array {
    // @todo leverage config for token replacement.
    $item = $this->getContextValue('item');
    assert($item instanceof ProductVariationInterface);

    $item_data = [
      'item_name' => $item->label(),
      'item_id' => $item->getSku(),
      'affiliation' => $this->currentStore->getStore()->label(),
    ];
    $data = [];
    // Commerce allows variations without a price, such as "call for price"
    // products. Leave the price out rather than report a value of zero.
    $price = $item->getPrice();
    if ($price !== NULL) {
      $data['currency'] = $price->getCurrencyCode();
      $data['value'] = $this->formatPriceNumber($price);
    }
    $data['items'] = [$item_data];
    return $data;
  }

}
