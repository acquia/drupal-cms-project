<?php

declare(strict_types=1);

namespace Drupal\custom_field_sdc\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
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
    $required = $component->metadata->schema['required'] ?? [];
    $is_valid = TRUE;
    $bubbleable_metadata = new BubbleableMetadata();
    $context = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity,
      'bubbleable_metadata' => $bubbleable_metadata,
    ];

    // Return early if the component is invalid and could result in a thrown
    // exception. Likely a missing dependency or malformed component.
    if (self::validateComponent($component) !== TRUE) {
      $this->loggerChannelFactory->get('custom_field_sdc')->error('The component %component_id failed validation and could not be rendered. Inspect the component configuration to fix the issue.', [
        '%component_id' => $component_id,
      ]);
      return;
    }

    $properties = \array_intersect_key($component_props, $prop_values);
    foreach ($properties as $property => $property_info) {
      $widget = $this->propWidgetManager->getPropWidget($property_info);
      if (!$widget) {
        continue;
      }
      $prop_value = $prop_values[$property] ?? NULL;
      if (!\is_array($prop_value) || !array_key_exists('value', $prop_value)) {
        continue;
      }

      $value = $widget->getPropValue($prop_value['value'], $context);

      if (in_array($property, $required) && ($value === NULL || $value === '')) {
        $is_valid = FALSE;
        break;
      }
      if ($value !== NULL && $value !== '') {
        $props[(string) $property] = $value;
      }
    }

    // If a prop is required and has no value, don't render the component.
    if (!$is_valid) {
      return;
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

    $original_metadata = BubbleableMetadata::createFromRenderArray($original_build);
    // Apply token cache metadata after the full output is assembled.
    $original_metadata->merge($bubbleable_metadata)->applyTo($output);

    $build = $output;
  }

}
