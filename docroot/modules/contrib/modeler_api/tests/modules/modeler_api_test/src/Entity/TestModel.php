<?php

declare(strict_types=1);

namespace Drupal\modeler_api_test\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines a model config entity for testing.
 *
 * This mirrors what a real model owner provides: a config entity carrying the
 * modeler API third-party settings, so that those settings can be exercised
 * against real config storage rather than a test double.
 *
 * @ConfigEntityType(
 *   id = "modeler_api_test_model",
 *   label = @Translation("Test Model"),
 *   config_prefix = "model",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 * )
 */
final class TestModel extends ConfigEntityBase {

  /**
   * The model ID.
   */
  protected string $id;

  /**
   * The model label.
   */
  protected string $label;

}
