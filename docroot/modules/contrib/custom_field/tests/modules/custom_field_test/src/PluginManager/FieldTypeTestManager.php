<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field_test\Attribute\TestFieldType;

/**
 * Provides the custom field type test plugin manager.
 */
class FieldTypeTestManager extends DefaultPluginManager implements FieldTypeTestManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new FieldTypeTestManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/CustomFieldTest/FieldType',
      $namespaces,
      $module_handler,
      'Drupal\custom_field_test\Plugin\FieldTypeTestInterface',
      TestFieldType::class
    );
    $this->setCacheBackend($cache_backend, 'custom_field_type_test_plugins');
  }

}
