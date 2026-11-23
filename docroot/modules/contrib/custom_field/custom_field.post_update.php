<?php

/**
 * @file
 * Post update functions for Custom Field.
 */

/**
 * Populates taxonomy_index with term references from custom fields.
 */
function custom_field_post_update_bulk_populate_taxonomy_index(array &$sandbox): void {
  $db = \Drupal::database();

  if (!\Drupal::moduleHandler()->moduleExists('taxonomy') ||
    !\Drupal::config('taxonomy.settings')->get('maintain_index_table')) {
    return;
  }

  // Initialize sandbox on first run.
  if (!isset($sandbox['last_nid'])) {
    $sandbox['last_nid'] = 0;
    $sandbox['processed_nodes'] = 0;
    $sandbox['merged_rows'] = 0;
    $sandbox['batch_size'] = 400;

    // Approximate total published nodes for progress bar.
    $sandbox['total_nodes'] = $db->select('node_field_data', 'n')
      ->condition('n.status', 1)
      ->countQuery()
      ->execute()
      ->fetchField() ?: 0;
  }

  // Fetch next batch of published node IDs.
  $nids_query = $db->select('node_field_data', 'n')
    ->fields('n', ['nid'])
    ->condition('n.status', 1)
    ->condition('n.nid', $sandbox['last_nid'], '>')
    ->orderBy('n.nid')
    ->range(0, $sandbox['batch_size']);

  $nids = $nids_query->execute()->fetchCol();

  // No more nodes → complete.
  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    \Drupal::messenger()->addStatus(t('taxonomy_index bulk population completed. Processed @nodes nodes and merged @rows rows.', [
      '@nodes' => $sandbox['processed_nodes'],
      '@rows'  => $sandbox['merged_rows'],
    ]));
    return;
  }

  // Load custom field storages once per batch.
  $storages = \Drupal::entityTypeManager()
    ->getStorage('field_storage_config')
    ->loadByProperties(['type' => 'custom', 'entity_type' => 'node']);

  $merged_this_batch = 0;

  foreach ($storages as $storage) {
    $field_name = $storage->getName();
    $field_table = 'node__' . $field_name;

    // Identify term reference sub-columns.
    $columns = $storage->getSetting('columns') ?? [];
    $term_columns = [];
    foreach ($columns as $sub_name => $column) {
      if ($column['type'] === 'entity_reference' && $column['target_type'] === 'taxonomy_term') {
        $term_columns[] = $field_name . '_' . $sub_name;
      }
    }

    if (empty($term_columns)) {
      continue;
    }

    foreach ($term_columns as $term_col) {
      $query = $db->select($field_table, 'f')
        ->condition('f.deleted', 0)
        ->condition('f.' . $term_col, NULL, 'IS NOT NULL')
        ->condition('f.entity_id', $nids, 'IN');

      $query->addField('f', 'entity_id', 'nid');
      $query->addField('f', $term_col, 'tid');

      $query->innerJoin('node_field_data', 'nfd',
        'nfd.nid = f.entity_id AND nfd.vid = f.revision_id AND nfd.langcode = f.langcode'
      );

      // Select only the node-level values we need (repeated per row but
      // limited to batch).
      $query->fields('nfd', ['created', 'sticky']);

      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($results as $row) {
        $nid = (int) $row['nid'];
        $tid = (int) $row['tid'];

        if ($tid <= 0) {
          continue;
        }

        $db->merge('taxonomy_index')
          ->keys([
            'nid' => $nid,
            'tid' => $tid,
            'status' => 1,
          ])
          ->fields([
            'sticky' => (int) ($row['sticky'] ?? 0),
            'created' => (int) ($row['created'] ?? 0),
          ])
          ->execute();

        $merged_this_batch++;
        $sandbox['merged_rows']++;
      }
    }
  }

  // Update sandbox state for next batch.
  $sandbox['processed_nodes'] += count($nids);
  $sandbox['last_nid'] = max($nids);

  // Calculate progress fraction.
  $sandbox['#finished'] = $sandbox['total_nodes'] > 0
    ? $sandbox['processed_nodes'] / $sandbox['total_nodes']
    : 0;

  \Drupal::messenger()->addStatus(t('Batch completed: processed @count nodes, merged @merged term relationships. Cumulative nodes processed: @total.', [
    '@count'  => count($nids),
    '@merged' => $merged_this_batch,
    '@total'  => $sandbox['processed_nodes'],
  ]));
}
