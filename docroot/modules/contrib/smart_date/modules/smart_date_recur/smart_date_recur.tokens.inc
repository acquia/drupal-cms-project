<?php

/**
 * @file
 * Provides tokens for the smart_date_recur module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\smart_date_recur\Hook\SmartDateRecurTokensHooks;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
#[LegacyHook]
function smart_date_recur_token_info() {
  return \Drupal::service(SmartDateRecurTokensHooks::class)->tokenInfo();
}

/**
 * Implements hook_tokens().
 */
#[LegacyHook]
function smart_date_recur_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  return \Drupal::service(SmartDateRecurTokensHooks::class)->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
