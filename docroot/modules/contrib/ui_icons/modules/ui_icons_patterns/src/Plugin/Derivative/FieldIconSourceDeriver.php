<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\Derivative;

use Drupal\Component\Plugin\PluginBase;
use Drupal\ui_patterns\Plugin\Derivative\EntityFieldSourceDeriverBase;
use Drupal\ui_patterns\PropTypePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides derivable context for every field property.
 */
class FieldIconSourceDeriver extends EntityFieldSourceDeriverBase {

  private const FIELD_TYPE = 'ui_icon';

  /**
   * The ui patterns prop type plugin manager.
   *
   * @var \Drupal\ui_patterns\PropTypePluginManager
   */
  protected ?PropTypePluginManager $propTypePluginManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $plugin = parent::create($container, $base_plugin_id);
    $plugin->propTypePluginManager = $container->get('plugin.manager.ui_patterns_prop_type');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeDefinitionsForEntityStorageFieldProperty(string $entity_type_id, string $field_name, string $property, array $base_plugin_derivative): void {
    if ($property !== 'target_id') {
      return;
    }

    if (!isset($this->entityFieldsMetadata[$entity_type_id]['field_storages'][$field_name]['metadata']['type'])) {
      return;
    }

    if ($this->entityFieldsMetadata[$entity_type_id]['field_storages'][$field_name]['metadata']['type'] !== self::FIELD_TYPE) {
      return;
    }

    $id = implode(PluginBase::DERIVATIVE_SEPARATOR, [
      $entity_type_id,
      $field_name,
      self::FIELD_TYPE,
    ]);
    $this->derivatives[$id] = array_merge(
      $base_plugin_derivative,
      [
        'id' => $id,
        'label' => $this->t('Icon (field)'),
        'prop_types' => ['icon', 'slot'],
        'context_requirements' => array_merge($base_plugin_derivative['context_requirements'], ['field_granularity:item']),
      ]);

  }

}
