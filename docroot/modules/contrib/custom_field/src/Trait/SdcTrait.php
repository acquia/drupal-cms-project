<?php

declare(strict_types=1);

namespace Drupal\custom_field\Trait;

use Drupal\Core\Plugin\Component;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

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
          $type = $prop['type'] ?? NULL;
          if ($type === 'array') {
            if (!isset($prop['items'])) {
              $invalid_reasons[] = $this->t('Missing items definition for %property prop.', ['%property' => $name]);
            }
            else {
              $items_type = $prop['items']['type'] ?? NULL;
              $ref = $prop['items']['$ref'] ?? NULL;
              if (!$items_type) {
                $invalid_reasons[] = $this->t('Missing items type definition for %property prop.', ['%property' => $name]);
              }
              elseif ($ref === 'json-schema-definitions://canvas.module/image') {
                $invalid_reasons[] = $this->t('The %module module is a dependency for this component.', [
                  '%module' => 'Canvas',
                ]);
              }
              elseif ($items_type === 'object') {
                if (!isset($prop['items']['properties'])) {
                  $invalid_reasons[] = $this->t('Missing items properties definition for %property prop.', ['%property' => $name]);
                }
              }
            }
          }
          if ($type === 'object') {
            $ref = $prop['$ref'] ?? NULL;
            if ($ref === 'json-schema-definitions://canvas.module/image') {
              $invalid_reasons[] = $this->t('The %module module is a dependency for this component.', [
                '%module' => 'Canvas',
              ]);
            }
            elseif (!isset($prop['properties'])) {
              $invalid_reasons[] = $this->t('Missing properties definition for %property prop.', ['%property' => $name]);
            }
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
   * Formats the prop value to expected type.
   *
   * @param array<string, mixed> $prop
   *   The prop definition.
   * @param array<string, mixed> $prop_value
   *   The value of the prop.
   *
   * @return mixed
   *   The formatted value for the prop.
   */
  protected static function formatPropValue(array $prop, array $prop_value): mixed {
    $type = $prop['type'] ?? NULL;
    $format = $prop['format'] ?? NULL;
    $id = $prop['id'] ?? NULL;
    $value = $prop_value['value'] ?? NULL;
    $widget = $prop_value['widget'] ?? NULL;
    if (!$type || ($value === NULL || $value === '') || $widget === NULL) {
      return NULL;
    }
    // The type can be an array, so we need to get the first element.
    if (\is_array($type)) {
      $type = reset($type);
    }
    // Build url value.
    if ($type === 'string' && \in_array($format, ['uri', 'uri-reference'])) {
      try {
        $url = Url::fromUri($value);
      }
      catch (\InvalidArgumentException $e) {
        $url = Url::fromRoute('<none>');
      }
      return $url->toString();
    }

    // Primitives.
    if ($type === 'integer') {
      return (int) $value;
    }
    if ($type === 'number') {
      return (float) $value;
    }
    if ($type === 'boolean') {
      return (bool) $value;
    }

    // Attribute.
    if ($type === 'Drupal\Core\Template\Attribute') {
      if (!\is_array($value)) {
        return NULL;
      }
      $attributes = \array_filter($value);
      return new Attribute($attributes);
    }

    // Recursive object.
    if ($type === 'object') {
      if ($id === 'json-schema-definitions://canvas.module/image') {
        return $value;
      }
      $properties = $prop['properties'] ?? [];
      $values = \is_array($value) ? $value : [];
      foreach ($values as $key => $prop_value) {
        $object_prop = $properties[$key] ?? NULL;
        $values[$key] = static::formatPropValue($object_prop, $prop_value);
      }
      return $values;
    }
    // Array.
    if ($type === 'array') {
      $items = $prop['items'] ?? [];
      $values = \is_array($value) ? $value : [];
      if (!\is_array($items) || empty($items)) {
        return NULL;
      }
      $items_type = $items['type'] ?? NULL;
      if (!$items_type) {
        return NULL;
      }
      if ($items_type === 'object') {
        $properties = $items['properties'] ?? [];
        $id = $items['id'] ?? NULL;
        if (empty($properties)) {
          return NULL;
        }
        foreach ($values as $delta => $prop_value_object) {
          if (!\is_array($prop_value_object)) {
            continue;
          }
          // Special case for canvas image.
          if ($id === 'json-schema-definitions://canvas.module/image') {
            $values[$delta] = static::formatPropValue($items, $prop_value_object['value'] ?? []);
          }
          else {
            foreach ($prop_value_object as $key => $prop_value) {
              $object_prop = $properties[$key] ?? NULL;
              $values[$delta][$key] = static::formatPropValue($object_prop, $prop_value);
            }
          }
        }
        return $values;
      }
      if ($items_type === 'integer') {
        return \array_map('intval', $values);
      }
      if ($items_type === 'number') {
        return \array_map('floatval', $values);
      }
      if ($items_type === 'string') {
        return $values;
      }

      return NULL;
    }

    return $value;
  }

}
