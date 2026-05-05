<?php

/**
 * @file
 * Contains post_update hooks for ai_agents.
 */

/**
 * Re-run update hook 10307 as it was skipped when updating from 1.1.x to 1.2.x.
 */
function ai_agents_post_update_10001() {
  $entity_type_manager = \Drupal::entityTypeManager();
  $storage = $entity_type_manager->getStorage('ai_agent');

  // Load all agents.
  $agents = $storage->loadMultiple();

  foreach ($agents as $agent) {
    $tool_usage_limits = $agent->get('tool_usage_limits') ?? [];
    $changed = FALSE;
    foreach ($tool_usage_limits as $tool_id => $properties) {
      // Clean up property restrictions.
      if (!is_array($properties) || empty($properties)) {
        continue;
      }
      foreach ($properties as $property_name => $values) {
        // Check if values exists.
        if (!empty($values['values']) && is_array($values['values'])) {
          foreach ($values['values'] as $key => $value) {
            // If the value ends with a \r, remove it.
            if (substr($value, -1) === "\r") {
              $tool_usage_limits[$tool_id][$property_name]['values'][$key] = rtrim($value, "\r");
              $changed = TRUE;
            }
            // Remove empty values.
            elseif ($value === '') {
              unset($tool_usage_limits[$tool_id][$property_name]['values'][$key]);
              $changed = TRUE;
            }
          }
        }
      }
    }
    // Set the cleaned tool usage limits back to the agent.
    if ($changed) {
      $agent->set('tool_usage_limits', $tool_usage_limits);
      $agent->save();
    }
  }
}
