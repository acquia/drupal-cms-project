<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides hooks related to config schemas.
 */
class EntityHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomFieldTypeManagerInterface $customFieldTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected PropWidgetManagerInterface $propWidgetManager,
    protected ComponentPluginManager $componentPluginManager,
  ) {}

  /**
   * Returns the maintain_index_table configuration value.
   */
  protected function shouldMaintainIndexTable(): bool {
    if (!$this->moduleHandler->moduleExists('taxonomy')) {
      return FALSE;
    }
    return (bool) $this->configFactory->get('taxonomy.settings')->get('maintain_index_table');
  }

  /**
   * Builds the taxonomy_index table for a node entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildNodeIndex(NodeInterface $node): void {
    if (!$this->shouldMaintainIndexTable() || !($this->entityTypeManager->getStorage('node') instanceof SqlContentEntityStorage)) {
      return;
    }
    $status = $node->isPublished();
    $sticky = (int) $node->isSticky();
    // We only maintain the taxonomy index for published nodes.
    if ($status && $node->isDefaultRevision()) {
      $tid_all = [];
      $custom_field_class = 'Drupal\custom_field\Plugin\Field\FieldType\CustomItem';
      foreach ($node->getFieldDefinitions() as $field) {
        $field_name = $field->getName();
        $is_custom_field_class = ($field->getItemDefinition()->getClass() === $custom_field_class);
        if ($is_custom_field_class) {
          $custom_items = $this->customFieldTypeManager->getCustomFieldItems($field->getSettings());
          $term_fields = [];
          foreach ($custom_items as $name => $custom_item) {
            $data_type = $custom_item->getDataType();
            if ($data_type === 'entity_reference' && $custom_item->getTargetType() === 'taxonomy_term') {
              $term_fields[] = $name;
            }
          }
          if (!empty($term_fields)) {
            foreach ($node->getTranslationLanguages() as $language) {
              foreach ($node->getTranslation($language->getId())->$field_name as $item) {
                if (!$item->isEmpty()) {
                  foreach ($term_fields as $term_field) {
                    if (!empty($item->$term_field)) {
                      $tid_all[$item->$term_field] = $item->$term_field;
                    }
                  }
                }
              }
            }
          }
        }
      }

      if (!empty($tid_all)) {
        foreach ($tid_all as $tid) {
          $this->database->merge('taxonomy_index')
            ->keys(['nid' => $node->id(), 'tid' => $tid, 'status' => $node->isPublished()])
            ->fields(['sticky' => $sticky, 'created' => $node->getCreatedTime()])
            ->execute();
        }
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $node): void {
    // Add taxonomy index entries for the node.
    assert($node instanceof NodeInterface);
    $this->buildNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $node): void {
    assert($node instanceof NodeInterface);
    // If we're not dealing with the default revision of the node,
    // do not make any change to the taxonomy index.
    if (!$node->isDefaultRevision()) {
      return;
    }
    $this->buildNodeIndex($node);
  }

  /**
   * Implements hook_entity_view_display_presave().
   */
  #[Hook('entity_view_display_presave')]
  public function entityViewDisplayPresave(EntityViewDisplayInterface $display): void {
    $dependencies = $display->getDependencies()['module'] ?? [];
    if (in_array('custom_field', $dependencies)) {
      foreach ($display->getComponents() as $field_name => $component) {
        if (!isset($component['type'])) {
          continue;
        }
        if ($component['type'] === 'custom_field_sdc') {
          $settings = $component['settings'] ?? [];
          $component_id = $settings['component'] ?: NULL;
          if (!$component_id) {
            continue;
          }
          try {
            $sdc_component = $this->componentPluginManager->find($component_id);
          }
          catch (ComponentNotFoundException) {
            continue;
          }
          $component_props = $sdc_component->metadata->schema['properties'] ?? [];
          $props = $settings['props'] ?? [];
          $did_change = FALSE;
          if (!empty($props)) {
            $did_change = TRUE;
          }
          foreach ($props as $prop_key => $prop_value) {
            $component_prop = $component_props[$prop_key] ?? NULL;
            // If the component does not have the property, remove it.
            if (!$component_prop) {
              unset($props[$prop_key]);
              continue;
            }
            $widget = $this->propWidgetManager->getPropWidget($component_prop);
            if (!$widget) {
              unset($props[$prop_key]);
              continue;
            }
            $massaged_value = $widget->massageValue($prop_value);
            $props[$prop_key] = $massaged_value;
          }
          if ($did_change) {
            $component['settings']['props'] = $props;
            $display->setComponent($field_name, $component);
          }
        }
      }
    }
  }

}
