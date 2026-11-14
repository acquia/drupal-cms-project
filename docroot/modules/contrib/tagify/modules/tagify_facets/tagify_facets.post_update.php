<?php

/**
 * @file
 * Post update functions for Tagify Facets.
 */

declare(strict_types=1);

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Recasts the Tagify facet widget "max_items" setting from string to integer.
 */
function tagify_facets_post_update_cast_max_items(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update(
    $sandbox,
    'facets_facet',
    static function ($facet): bool {
      $widget = $facet->getWidget();
      if (($widget['type'] ?? NULL) !== 'tagify') {
        return FALSE;
      }
      // Facets saved before the widget had config schema stored max_items
      // verbatim, so the string from the #type => number element persisted.
      // Re-saving lets Config::castValue() coerce it to the declared integer.
      return isset($widget['config']['max_items'])
        && !is_int($widget['config']['max_items']);
    }
  );
}
