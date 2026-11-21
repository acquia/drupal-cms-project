<?php

/**
 * @file
 * Post update functions for the Menu Link Attributes module.
 */

/**
 * Restore container attributes wrongly removed by update 8004.
 *
 * Update 8004 also removed safe container attributes like "container_class".
 * Restore the definitions of those still stored on menu links, see
 * https://www.drupal.org/i/3590635.
 */
function menu_link_attributes_post_update_restore_container_attributes(&$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute();
    $sandbox['current'] = 0;
    $sandbox['last_id'] = 0;
    $sandbox['attribute_names'] = [];
  }

  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('id', $sandbox['last_id'], '>')
    ->sort('id')
    ->range(0, 50)
    ->execute();

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menu_link */
  foreach ($storage->loadMultiple($ids) as $menu_link) {
    foreach (array_keys($menu_link->getTranslationLanguages()) as $langcode) {
      $link = $menu_link->getTranslation($langcode)->link;
      $container_attributes = $link->isEmpty() ? [] : ($link->first()->options['container_attributes'] ?? []);
      if (is_array($container_attributes)) {
        $sandbox['attribute_names'] += array_fill_keys(array_keys($container_attributes), TRUE);
      }
    }
    $sandbox['last_id'] = $menu_link->id();
  }
  $storage->resetCache($ids);
  $sandbox['current'] += count($ids);

  $sandbox['#finished'] = $ids && $sandbox['current'] < $sandbox['total'] ? $sandbox['current'] / $sandbox['total'] : 1;
  if ($sandbox['#finished'] < 1) {
    return;
  }

  $config = \Drupal::configFactory()->getEditable('menu_link_attributes.config');
  $attributes = $config->get('attributes') ?: [];
  $stored = menu_link_attributes_get_stored_attribute_names($attributes);
  $restored = [];

  foreach (array_keys($sandbox['attribute_names']) as $attribute_name) {
    $attribute = 'container_' . $attribute_name;
    // Also skip attributes defined without prefix but with "container: true",
    // see
    // https://git.drupalcode.org/project/menu_link_attributes/-/issues/3590636.
    if (isset($attributes[$attribute]) || isset($stored['container_attributes'][$attribute_name]) || !menu_link_attributes_is_allowed_attribute_name($attribute_name)) {
      continue;
    }

    $attributes[$attribute] = [];
    if ($attribute === 'container_class') {
      $attributes[$attribute] = [
        'label' => 'Container class(es)',
        'description' => 'CSS class for the menu list item (<code>&lt;li&gt;</code>). Separate multiple classes by space.',
      ];
    }
    $restored[] = $attribute;
  }

  if ($restored) {
    $config->set('attributes', $attributes)->save();
    return t('Restored container attributes in configuration: @attributes', ['@attributes' => implode(', ', $restored)]);
  }
}

/**
 * Migrate attribute names with underscores to hyphens.
 *
 * Update 8004 removed attributes like "menu_color" instead of renaming them.
 * Rename the values stored on menu links and restore missing definitions,
 * see https://git.drupalcode.org/project/menu_link_attributes/-/issues/3590636.
 */
function menu_link_attributes_post_update_migrate_underscore_attributes(&$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute();
    $sandbox['current'] = 0;
    $sandbox['last_id'] = 0;
    $sandbox['updated'] = 0;
    $sandbox['migrated'] = [];
  }

  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('id', $sandbox['last_id'], '>')
    ->sort('id')
    ->range(0, 50)
    ->execute();

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menu_link */
  foreach ($storage->loadMultiple($ids) as $menu_link) {
    $changed = FALSE;
    foreach (array_keys($menu_link->getTranslationLanguages()) as $langcode) {
      $link = $menu_link->getTranslation($langcode)->link;
      if ($link->isEmpty()) {
        continue;
      }

      $options = $link->first()->options;
      foreach (['attributes', 'container_attributes'] as $group) {
        if (empty($options[$group]) || !is_array($options[$group])) {
          continue;
        }

        $migrated_attributes = [];
        foreach ($options[$group] as $attribute => $value) {
          $attribute = (string) $attribute;
          $sanitized_attribute = menu_link_attributes_sanitize_attribute_name($attribute);
          if ($sanitized_attribute === $attribute || !menu_link_attributes_is_allowed_attribute_name(preg_replace('/^container_/', '', $sanitized_attribute))) {
            $migrated_attributes[$attribute] = $value;
            continue;
          }

          // A value already stored under the new name is the current one.
          if (!isset($options[$group][$sanitized_attribute])) {
            $migrated_attributes[$sanitized_attribute] = $value;
          }
          $sandbox['migrated'][$group][$sanitized_attribute] = $attribute;
        }

        if ($migrated_attributes !== $options[$group]) {
          $options[$group] = $migrated_attributes;
          $link->first()->options = $options;
          $changed = TRUE;
        }
      }
    }

    if ($changed) {
      $menu_link->save();
      $sandbox['updated']++;
    }
    $sandbox['last_id'] = $menu_link->id();
  }
  $storage->resetCache($ids);
  $sandbox['current'] += count($ids);

  $sandbox['#finished'] = $ids && $sandbox['current'] < $sandbox['total'] ? $sandbox['current'] / $sandbox['total'] : 1;
  if ($sandbox['#finished'] < 1) {
    return;
  }

  $config = \Drupal::configFactory()->getEditable('menu_link_attributes.config');
  $attributes = $config->get('attributes') ?: [];

  $stored = menu_link_attributes_get_stored_attribute_names($attributes);

  $restored = [];
  foreach ($sandbox['migrated'] as $group => $migrated_attributes) {
    foreach (array_keys($migrated_attributes) as $attribute) {
      if (isset($stored[$group][$attribute])) {
        continue;
      }
      $attribute = $group === 'container_attributes' ? 'container_' . $attribute : $attribute;
      $attributes[$attribute] = [];
      $restored[] = $attribute;
    }
  }

  // The restore post update of 8.x-1.9 added empty "container_*" definitions
  // also for attributes defined without prefix but with "container: true".
  $removed = [];
  foreach ($stored['container_attributes'] as $attribute_name => $configured_attributes) {
    $duplicate = 'container_' . $attribute_name;
    if (count($configured_attributes) > 1 && in_array($duplicate, $configured_attributes, TRUE) && $attributes[$duplicate] === []) {
      unset($attributes[$duplicate]);
      $removed[] = $duplicate;
    }
  }

  if ($restored || $removed) {
    $config->set('attributes', $attributes)->save();
  }

  $messages = [];
  if ($sandbox['updated']) {
    $messages[] = \Drupal::translation()->formatPlural($sandbox['updated'], 'Replaced underscores with hyphens in attribute names of one menu link.', 'Replaced underscores with hyphens in attribute names of @count menu links.');
    $renamed = [];
    foreach ($sandbox['migrated'] as $migrated_attributes) {
      $renamed += array_flip($migrated_attributes);
    }
    $messages[] = menu_link_attributes_log_renamed_attributes($renamed, 'menu links');
  }
  if ($restored) {
    $messages[] = t('Restored menu link attributes in configuration: @attributes. Their labels and options were lost and need to be re-added.', ['@attributes' => implode(', ', $restored)]);
  }
  if ($removed) {
    $messages[] = t('Removed duplicate empty menu link attributes from configuration: @attributes', ['@attributes' => implode(', ', $removed)]);
  }
  if ($messages) {
    return implode(' ', $messages);
  }
}
