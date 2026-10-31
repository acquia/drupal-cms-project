<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Provides the custom field feeds plugin manager.
 */
class CustomFieldFeedsManager extends DefaultPluginManager implements CustomFieldFeedsManagerInterface {

  use StringTranslationTrait;

  /**
   * The custom field type manager.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected CustomFieldTypeManagerInterface $customFieldTypeManager;

  /**
   * Constructs a new CustomFieldFeedsManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface $custom_field_type_manager
   *   The custom field type manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, CustomFieldTypeManagerInterface $custom_field_type_manager) {
    parent::__construct(
      'Plugin/CustomField/FeedsType',
      $namespaces,
      $module_handler,
      'Drupal\custom_field\Plugin\CustomFieldFeedsTypeInterface',
      CustomFieldFeedsType::class,
      'Drupal\custom_field\Annotation\CustomFieldFeedsType'
    );

    $this->alterInfo('custom_field_feeds_info');
    $this->setCacheBackend($cache_backend, 'custom_field_feeds_plugins');
    $this->customFieldTypeManager = $custom_field_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedsTargets(array $settings): array {
    $custom_items = $this->customFieldTypeManager->getCustomFieldItems($settings);
    $items = [];

    foreach ($custom_items as $name => $custom_item) {
      $type = $custom_item->getDataType();
      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldFeedsTypeInterface $instance */
        $instance = $this->createInstance($type, $custom_item->getSettings());
        $items[$name] = $instance;
        if (in_array($type, ['daterange', 'time_range'])) {
          $items[$name . '__end'] = $instance;
        }
      }
      catch (PluginException $e) {
        // Should we log the error?
      }
    }

    return $items;
  }

}
