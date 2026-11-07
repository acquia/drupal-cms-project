<?php

namespace Drupal\eca_migrate_test\Plugin\migrate\id_map;

use Drupal\migrate\Plugin\migrate\id_map\NullIdMap;

/**
 * Defines the eca_migrate_test id map implementation.
 */
class TestIdMap extends NullIdMap {

  /**
   * Helper variable to check if prepareUpdate() was called.
   *
   * @var bool
   */
  public static bool $prepareUpdateCalled = FALSE;

  /**
   * {@inheritdoc}
   */
  public function prepareUpdate(): void {
    self::$prepareUpdateCalled = TRUE;
    parent::prepareUpdate();
  }

}
