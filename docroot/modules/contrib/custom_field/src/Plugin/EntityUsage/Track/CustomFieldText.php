<?php

namespace Drupal\custom_field\Plugin\EntityUsage\Track;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\Attribute\EntityUsageTrack;
use Drupal\entity_usage\EntityUsageTrackManager;
use Drupal\entity_usage\Plugin\EntityUsage\Track\TextFieldEmbedBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tracks usage of entities related in custom fields with text items.
 */
#[EntityUsageTrack(
  id: 'custom_field_text',
  label: new TranslatableMarkup('Custom Field Text'),
  description: new TranslatableMarkup("Tracks relationships created with 'Custom Field' Text (long) sub-fields."),
  field_types: ['custom'],
  source_entity_class: FieldableEntityInterface::class,
)]
class CustomFieldText extends TextFieldEmbedBase {

  /**
   * The entity usage track manager.
   *
   * @var \Drupal\entity_usage\EntityUsageTrackManager
   */
  protected EntityUsageTrackManager $trackManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->trackManager = $container->get('plugin.manager.entity_usage.track');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    $text = $this->getTextFromField($item);
    if (empty($text)) {
      return [];
    }

    $entities_in_text = $this->parseEntitiesFromText($text);
    $valid_entities = [];

    $uuids_by_type = [];
    foreach ($entities_in_text as $uuid => $entity_type) {
      // If the entity's existence has already been checked, then do not recheck
      // this.
      if (str_starts_with($entity_type, self::VALID_ENTITY_ID_PREFIX)) {
        $valid_entities[] = substr($entity_type, strlen(self::VALID_ENTITY_ID_PREFIX));
      }
      else {
        $uuids_by_type[$entity_type][] = $uuid;
      }
    }

    foreach ($uuids_by_type as $entity_type => $uuids) {
      $target_type = $this->entityTypeManager->getDefinition($entity_type);
      // Check if the target entity exists since text fields are not
      // automatically updated when an entity is removed.
      $query = $this->entityTypeManager->getStorage($entity_type)
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition($target_type->getKey('uuid'), $uuids, 'IN');
      $valid_entities = \array_merge($valid_entities, \array_values(\array_unique(\array_map(fn ($id) => $entity_type . '|' . $id, $query->execute()))));
    }

    return $valid_entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTextFromField(FieldItemInterface $item): string {
    $text = '';
    $field = $item->getFieldDefinition();
    $columns = $field->getSetting('columns');
    foreach ($columns as $name => $column) {
      if ($column['type'] === 'string_long') {
        $text .= $item->{$name} ?? '';
      }
    }
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function parseEntitiesFromText($text): array {
    $result = [];
    // Run all text field plugins on this text and return the result.
    $plugins = $this->getEnabledPlugins();
    foreach ($plugins as $plugin) {
      if ($plugin instanceof TextFieldEmbedBase) {
        $result = \array_merge($result, $plugin->parseEntitiesFromText($text));
      }
    }
    return $result;
  }

  /**
   * Gets the enabled tracking plugins, all plugins are enabled by default.
   *
   * @return array<string, \Drupal\entity_usage\EntityUsageTrackInterface>
   *   The enabled plugin instances keyed by plugin ID.
   *
   * @todo File an issue to make \Drupal\entity_usage\EntityUpdateManager::getEnabledPlugins() a public method?
   */
  protected function getEnabledPlugins(): array {
    $all_plugin_ids = \array_keys($this->trackManager->getDefinitions());
    // Do not include myself.
    $all_plugin_ids = \array_diff($all_plugin_ids, [$this->getPluginId()]);
    $enabled_plugins = $this->config->get('track_enabled_plugins');
    $enabled_plugin_ids = \is_array($enabled_plugins) ? $enabled_plugins : $all_plugin_ids;

    $plugins = [];
    foreach (\array_intersect($all_plugin_ids, $enabled_plugin_ids) as $plugin_id) {
      try {
        $plugin = $this->trackManager->createInstance($plugin_id);
        $plugins[$plugin_id] = $plugin;
      }
      catch (\Exception $e) {
        // Do nothing.
      }
    }

    return $plugins;
  }

}
