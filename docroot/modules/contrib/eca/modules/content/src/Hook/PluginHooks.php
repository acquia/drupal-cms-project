<?php

namespace Drupal\eca_content\Hook;

use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Plugin\Action\ActionInterface;
use Drupal\eca_content\Plugin\Action\CoreFieldUpdateAction;

/**
 * Implements plugin hooks for the ECA Content submodule.
 */
class PluginHooks {

  /**
   * Implements hook_action_info_alter().
   *
   * Action plugins built on core's Drupal\Core\Field\FieldUpdateActionBase save
   * the entity right inside execute() and dereference the given object in
   * access() without a guard. Neither is acceptable within an ECA context, so
   * every such plugin is remapped onto
   * Drupal\eca_content\Plugin\Action\CoreFieldUpdateAction, which reproduces
   * the very same field map with ECA's semantics.
   *
   * Keying on the subclass relation rather than on a hardcoded list of plugin
   * IDs keeps contrib actions that extend core's base class covered, both the
   * present and the future ones.
   */
  #[Hook('action_info_alter')]
  public function actionInfoAlter(array &$definitions): void {
    foreach ($definitions as &$definition) {
      $class = $definition['class'] ?? NULL;
      if (!is_string($class) || $class === '' || !class_exists($class)) {
        continue;
      }
      // ECA's own actions are already correct. That includes SetFieldValue,
      // which extends ECA's replacement base class by its real name. Skipping
      // them also makes this remap idempotent.
      if (is_a($class, ActionInterface::class, TRUE)) {
        continue;
      }
      if (is_subclass_of($class, FieldUpdateActionBase::class)) {
        $definition['eca_original_class'] = $class;
        $definition['class'] = CoreFieldUpdateAction::class;
      }
    }
  }

}
