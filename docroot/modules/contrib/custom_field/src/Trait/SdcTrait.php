<?php

declare(strict_types=1);

namespace Drupal\custom_field\Trait;

use Drupal\Core\Plugin\Component;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;

/**
 * Trait for 'custom_field_sdc' field formatter plugin.
 */
trait SdcTrait {

  /**
   * Validates a component.
   *
   * @param \Drupal\Core\Plugin\Component $component
   *   The component to validate.
   *
   * @return bool|\Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Returns an array of reasons why the component is invalid, or TRUE if the
   *   component is valid.
   */
  protected function validateComponent(Component $component): array|bool {
    $invalid_reasons = [];
    $props = $component->getPluginDefinition()['props'] ?? NULL;
    if (!$props) {
      $invalid_reasons[] = $this->t('Missing props definition.');
    }
    else {
      $properties = $props['properties'] ?? NULL;
      if ($properties === NULL) {
        $invalid_reasons[] = $this->t('Missing <em>properties</em> definition.');
      }
      else {
        foreach ($properties as $name => $prop) {
          $validations = static::isValidProp($name, $prop);
          if (!empty($validations)) {
            $invalid_reasons = array_merge($invalid_reasons, $validations);
          }
        }
      }
    }
    if (!empty($invalid_reasons)) {
      return $invalid_reasons;
    }

    return TRUE;
  }

  /**
   * Invalid prop message helper function.
   *
   * @param string $name
   *   Prop name.
   * @param string $reason
   *   Reason.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated message.
   */
  private static function invalidPropMessage(string $name, string $reason) {
    return t('Invalid prop %name. @reason', [
      '%name' => $name,
      '@reason' => $reason,
    ]);
  }

  /**
   * Validates a prop.
   *
   * @param string $name
   *   Prop name.
   * @param array $prop
   *   The prop definition.
   *
   * @return array
   *   Array of invalid reasons.
   */
  protected static function isValidProp(string $name, array $prop) {
    $type = $prop['type'] ?? NULL;
    $ref = $prop['$ref'] ?? NULL;
    $reasons = [];
    if (!$type) {
      $reasons[] = self::invalidPropMessage($name, 'Missing type definition.');
    }
    elseif ($type === 'object' && $ref === PropWidgetManagerInterface::CANVAS_IMAGE) {
      $reasons[] = self::invalidPropMessage($name, 'The Canvas module is a dependency for this prop.');
    }
    elseif ($type === 'array') {
      if (!isset($prop['items'])) {
        $reasons[] = self::invalidPropMessage($name, 'Missing items definition.');
      }
      else {
        $items_type = $prop['items']['type'] ?? NULL;
        $items_ref = $prop['items']['$ref'] ?? NULL;
        if (!$items_type) {
          $reasons[] = self::invalidPropMessage($name, 'Missing items type definition.');
        }
        elseif ($items_type === 'object') {
          if ($items_ref === PropWidgetManagerInterface::CANVAS_IMAGE) {
            $reasons[] = self::invalidPropMessage($name, 'The Canvas module is a dependency for this prop.');
          }
          elseif (!isset($prop['items']['properties'])) {
            $reasons[] = self::invalidPropMessage($name, 'Missing items properties definition.');
          }
          else {
            $items_properties = $prop['items']['properties'] ?? [];
            foreach ($items_properties as $items_name => $items_prop) {
              $items_valid = self::isValidProp($name . ':' . $items_name, $items_prop);
              $reasons = array_merge($reasons, $items_valid);
            }
          }
        }
      }
    }

    return $reasons;
  }

}
