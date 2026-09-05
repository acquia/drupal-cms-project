<?php

/**
 * @file
 * Provides tokens for the smart_date module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\smart_date\Hook\SmartDateTokensHooks;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
#[LegacyHook]
function smart_date_token_info() {
  return \Drupal::service(SmartDateTokensHooks::class)->tokenInfo();
}

/**
 * Implements hook_tokens().
 */
#[LegacyHook]
function smart_date_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  return \Drupal::service(SmartDateTokensHooks::class)->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
