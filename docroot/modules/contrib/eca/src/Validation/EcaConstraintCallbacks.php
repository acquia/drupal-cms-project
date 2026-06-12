<?php

namespace Drupal\eca\Validation;

use Drupal\Core\TypedData\Plugin\DataType\LanguageReference;

/**
 * Provides static callbacks for schema validation.
 */
class EcaConstraintCallbacks {

  /**
   * Returns all valid langcodes for ECA content.
   *
   * @return string[]
   *   An array of valid langcodes.
   */
  public static function getAllValidLangcodes(): array {
    return array_merge(LanguageReference::getAllValidLangcodes(), ['_interface', '_eca_token', '']);
  }

  /**
   * Returns all valid content entity types.
   *
   * @return int[]|string[]
   *   All valid content entity types.
   */
  public static function getAllValidContentEntityTypes(): array {
    return array_keys(\Drupal::service('eca.service.content_entity_types')->getTypes());
  }

  /**
   * Returns all valid entity types and "_none".
   *
   * @return int[]|string[]
   *   All valid content entity types.
   */
  public static function getAllValidContentEntityTypesAndNone(): array {
    $entity_type_ids = self::getAllValidContentEntityTypes();
    $entity_type_ids[] = '_none';
    return $entity_type_ids;
  }

  /**
   * Returns all valid content entity types and bundles.
   *
   * @return int[]|string[]
   *   All valid content entity types and bundles.
   */
  public static function getAllValidContentEntityTypesAndBundles(): array {
    return array_keys(\Drupal::service('eca.service.content_entity_types')->getTypesAndBundles());
  }

  /**
   * Returns all valid content entity types (incl. any) and bundles.
   *
   * @return int[]|string[]
   *   All valid content entity types (incl. any) and bundles.
   */
  public static function getAllValidContentEntityTypesAndBundlesIncludeAny(): array {
    return array_keys(\Drupal::service('eca.service.content_entity_types')->getTypesAndBundles(TRUE));
  }

  /**
   * Returns all valid content entity types and bundles without bundle any.
   *
   * @return int[]|string[]
   *   All valid content entity types and bundles without bundle any.
   */
  public static function getAllValidContentEntityTypesAndBundlesExcludeBundleAny(): array {
    return array_keys(\Drupal::service('eca.service.content_entity_types')->getTypesAndBundles(FALSE, FALSE));
  }

  /**
   * Returns all valid data types for tool arguments.
   *
   * This includes primitive typed data types (string, integer, boolean, etc.)
   * as well as entity data types (entity:node, entity:node:article, etc.).
   *
   * @return string[]
   *   Associative array of data type ID => human-readable label.
   */
  public static function getAllValidDataTypes(): array {
    $definitions = \Drupal::typedDataManager()->getDefinitions();
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = (string) ($definition['label'] ?? $id);
    }
    asort($options);
    return $options;
  }

}
