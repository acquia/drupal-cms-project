<?php

/**
 * @file
 * Provides views data for the smart_date module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\smart_date\Hook\SmartDateViewsHooks;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
#[LegacyHook]
function smart_date_field_views_data(FieldStorageConfigInterface $field) {
  return \Drupal::service(SmartDateViewsHooks::class)->fieldViewsData($field);
}
