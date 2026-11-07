<?php

declare(strict_types=1);

namespace Drupal\modeler_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the model entity type.
 *
 * @ConfigEntityType(
 *   id = "modeler_api_data_model",
 *   label = @Translation("Data Model"),
 *   label_collection = @Translation("Data Models"),
 *   label_singular = @Translation("data model"),
 *   label_plural = @Translation("data models"),
 *   label_count = @PluralTranslation(
 *     singular = "@count data model",
 *     plural = "@count data models",
 *   ),
 *   config_prefix = "data_model",
 *   admin_permission = "administer modeler_api_data_model",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   config_export = {
 *     "id",
 *     "data",
 *   },
 * )
 */
final class DataModel extends ConfigEntityBase {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $data;

}
