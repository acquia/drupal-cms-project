<?php

namespace Drupal\custom_field\Trait;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides token support for PropWidget plugins.
 */
trait PropWidgetTokenTrait {

  /**
   * Gets the token type for an entity type ID.
   *
   * Uses the token entity mapper service when available to correctly resolve
   * entity type IDs to their token type equivalents, e.g. 'taxonomy_term'
   * maps to 'term'.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string
   *   The token type.
   */
  protected function getTokenType(string $entity_type_id): string {
    if (\Drupal::hasService('token.entity_mapper')) {
      return \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($entity_type_id);
    }
    return $entity_type_id;
  }

  /**
   * Resolves token values in a string.
   *
   * @param string $value
   *   The value potentially containing tokens.
   * @param array<string, mixed> $context
   *   The contextual data for the widget.
   *   - entity_type: The entity type ID for token support.
   *   - entity: The entity object for token replacement.
   *
   * @return string|null
   *   The resolved value, or NULL if empty after resolution.
   */
  protected function resolveTokens(string $value, array $context = []): ?string {
    if (empty($value)) {
      return NULL;
    }

    // If no tokens present, return as-is.
    if (!str_contains($value, '[')) {
      return $value;
    }

    // If token module is not installed, return the raw value as-is rather
    // than returning NULL, since the string may still be valid without
    // token resolution.
    if (!\Drupal::moduleHandler()->moduleExists('token')) {
      return $value;
    }

    $entity_type_id = $context['entity_type'] ?? NULL;
    $entity = $context['entity'] ?? NULL;
    $bubbleable_metadata = $context['bubbleable_metadata'] ?? NULL;

    $data = [];
    if ($entity_type_id && $entity) {
      $token_type = $this->getTokenType($entity_type_id);
      $data[$token_type] = $entity;
    }

    $resolved = \Drupal::service('token')->replace(
      $value,
      $data,
      ['clear' => TRUE],
      $bubbleable_metadata instanceof BubbleableMetadata ? $bubbleable_metadata : new BubbleableMetadata(),
    );

    return $resolved !== '' ? $resolved : NULL;
  }

  /**
   * Adds token browser UI to a form element.
   *
   * @param array<string, mixed> $element
   *   The form element to add token support to.
   * @param array<string, mixed> $context
   *   The contextual data for the widget. May include:
   *   - entity_type: The entity type ID for token support.
   *   - entity: The entity object for token replacement.
   *
   * @return array<string, mixed>
   *   The element with token support added.
   */
  protected function addTokenBrowser(array $element, array $context = []): array {
    if (!\Drupal::moduleHandler()->moduleExists('token')) {
      return $element;
    }

    // Prefer token_browser module if available.
    $token_browser_enabled = \Drupal::moduleHandler()->moduleExists('token_browser');
    $token_types = [];
    $entity_type_id = $context['entity_type'] ?? NULL;
    if ($entity_type_id) {
      $token_types[] = $this->getTokenType($entity_type_id);
    }

    $element['token_help'] = [
      '#theme' => $token_browser_enabled ? 'token_browser_link' : 'token_tree_link',
      '#token_types' => $token_types,
      '#global_types' => TRUE,
      '#weight' => 100,
    ];

    if (!$token_browser_enabled) {
      $element['token_help']['#recursion_limit'] = 3;
      $element['token_help']['#recursion_limit_max'] = 6;
    }

    return $element;
  }

}
