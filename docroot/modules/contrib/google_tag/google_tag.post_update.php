<?php

/**
 * @file
 * Google Tag updates once other modules have made their own updates.
 */

/**
 * Update Google GTM Advanced updates entity.
 */
function google_tag_post_update_move_advanced_settings() {
  $google_tags = \Drupal::entityTypeManager()->getStorage('google_tag_container')->loadMultiple();
  /** @var \Drupal\google_tag\Entity\TagContainer $tag_container */
  foreach ($google_tags as $tag_container) {
    $advanced_settings = $tag_container->get('advanced_settings') ?? [];
    $gtm_settings = $advanced_settings['gtm'] ?? [];
    // Before 2.0.3 the GTM settings were one flat set of values. Settings that
    // are already keyed by GTM ID need no change.
    if (!$gtm_settings || is_array(reset($gtm_settings))) {
      continue;
    }
    unset($advanced_settings['gtm']);
    foreach ($tag_container->getGtmIds() as $gtm_id) {
      $advanced_settings['gtm'][$gtm_id] = $gtm_settings;
    }
    $tag_container->set('advanced_settings', $advanced_settings);
    $tag_container->save();
  }
}

/**
 * Rebuild the container to register the delayed event request policy.
 */
function google_tag_post_update_add_delayed_event_request_policy() {
}

/**
 * Clear caches so the container listing can be ordered by weight.
 */
function google_tag_post_update_container_weight_key() {
  // Empty post-update hook: the entity type definition gained a weight key.
}
