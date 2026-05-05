<?php

declare(strict_types=1);

namespace Drupal\custom_field_sdc\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use Drupal\custom_field\Trait\SdcTrait;

/**
 * Provides hooks related to entities.
 */
class EntityHooks {

  use StringTranslationTrait;
  use SdcTrait;

  public function __construct(
    protected ComponentPluginManager $componentManager,
    protected PropWidgetManagerInterface $propWidgetManager,
  ) {}

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    // Return early if the sdc_display module is overriding the display.
    $sdc_display_enabled = (bool) $display->getThirdPartySetting('sdc_display', 'enabled');
    if ($sdc_display_enabled) {
      return;
    }
    $settings = $display->getThirdPartySetting('custom_field_sdc', 'settings', []);
    $enabled = !empty($settings['enabled']);
    $prop_values = $settings['props'] ?? [];
    $slot_values = $settings['slots'] ?? [];
    $props = [];
    if (!$enabled) {
      return;
    }
    $component_id = $settings['component'] ?? NULL;
    if (empty($component_id)) {
      return;
    }
    try {
      $component = $this->componentManager->find($component_id);
    }
    catch (ComponentNotFoundException $e) {
      return;
    }
    $component_props = $component->metadata->schema['properties'] ?? [];
    $properties = \array_intersect_key($component_props, $prop_values);
    foreach ($properties as $property => $property_info) {
      $value = static::formatPropValue($property_info, $prop_values[$property]);
      if ($value !== NULL) {
        $props[(string) $property] = $value;
      }
    }
    $entity_type = $entity->getEntityTypeId();
    $original_build = $build;
    $output = [
      '#entity_type' => $entity_type,
      '#' . $entity_type => $entity,
      '#view_mode' => $original_build['#view_mode'] ?? 'default',
      'component' => [
        '#type' => 'component',
        '#component' => $component_id,
        '#slots' => [],
        '#props' => $props,
      ],
    ];

    foreach ($slot_values as $slot_name => $slot_value) {
      $slot_source = $slot_value['source'] ?? 'field';
      $slot_field = $slot_value['field'] ?? '';
      $slot_value = $original_build[$slot_field] ?? '';
      if ($slot_source === 'field') {
        $output['component']['#slots'][$slot_name] = $slot_value;
      }
    }
    BubbleableMetadata::createFromRenderArray($original_build)->applyTo($output);
    $build = $output;
  }

}
