<?php

/**
 * @file
 * Provides views data for the entity_reference_revisions module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\entity_reference_revisions\Hook\EntityReferenceRevisionsViewsHooks;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
#[LegacyHook]
function entity_reference_revisions_field_views_data(FieldStorageConfigInterface $field_storage) {
  return \Drupal::service(EntityReferenceRevisionsViewsHooks::class)->fieldViewsData($field_storage);
}
