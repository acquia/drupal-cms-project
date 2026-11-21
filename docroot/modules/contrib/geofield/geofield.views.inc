<?php

/**
 * @file
 * Views hook implementations.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\geofield\Hook\GeofieldViewsHooks;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
#[LegacyHook]
function geofield_field_views_data(FieldStorageConfigInterface $field_storage) {
  return \Drupal::service(GeofieldViewsHooks::class)->fieldViewsData($field_storage);
}
