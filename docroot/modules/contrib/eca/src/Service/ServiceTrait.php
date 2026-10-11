<?php

namespace Drupal\eca\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Trait for ECA modeller, condition and action services.
 */
trait ServiceTrait {

  use StringTranslationTrait;

  /**
   * Helper function to sort plugins by their label.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $plugins
   *   The list of plugin to be sorted.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensions
   *   The module extension manager.
   */
  public function sortPlugins(array &$plugins, ModuleExtensionList $extensions): void {
    usort($plugins, static function ($p1, $p2) use ($extensions) {
      $provider1 = $p1->getPluginDefinition()['provider'] ?? 'eca';
      $provider2 = $p2->getPluginDefinition()['provider'] ?? 'eca';
      $m1 = $provider1 === 'core' ? 'Drupal Core' : $extensions->getName($provider1);
      $m2 = $provider2 === 'core' ? 'Drupal Core' : $extensions->getName($provider2);
      if ($m1 < $m2) {
        return -1;
      }
      if ($m1 > $m2) {
        return 1;
      }
      $l1 = (string) $p1->getPluginDefinition()['label'];
      $l2 = (string) $p2->getPluginDefinition()['label'];
      if ($l1 < $l2) {
        return -1;
      }
      if ($l1 > $l2) {
        return 1;
      }
      return 0;
    });
  }

  /**
   * Builds a field label from the key.
   *
   * @param string $key
   *   The key of the field from which to build a label.
   *
   * @return string
   *   The built label for the field identified by key.
   */
  public static function convertKeyToLabel(string $key): string {
    $labelParts = explode('_', $key);
    $labelParts[0] = ucfirst($labelParts[0]);
    return implode(' ', $labelParts);
  }

}
