<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\EntityOwnerInterface;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Builds View configurations dynamically for trash listings.
 */
class TrashViewBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Builds and configures a View executable for listing deleted entities.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to build a view for.
   * @param bool $export
   *   Whether the view will be exported as an entity.
   *
   * @return \Drupal\views\ViewExecutable
   *   A View executable configured to display deleted entities.
   */
  public function buildView(EntityTypeInterface $entity_type, bool $export = FALSE): ViewExecutable {
    $entity_type_id = $entity_type->id();
    $base_table = $this->getBaseTable($entity_type);

    // Create a minimal View entity.
    $view = View::create([
      'id' => 'trash_' . $entity_type_id,
      'label' => $this->t('Trash: @entity_type', ['@entity_type' => $entity_type->getLabel()]),
      'description' => $this->t('Find and manage trashed @items.', ['@items' => $entity_type->getPluralLabel()]),
      'base_table' => $base_table,
      'base_field' => $entity_type->getKey('id'),
    ]);

    // Get the executable and configure it using the handler API.
    $executable = Views::executableFactory()->get($view);
    $executable->setDisplay('default');

    // Configure display options.
    $display = $executable->getDisplay();
    $display->setOption('title', (string) $this->t('Deleted @label', ['@label' => $entity_type->getPluralLabel()]));
    $display->setOption('access', [
      'type' => 'perm',
      'options' => ['perm' => 'access trash'],
    ]);
    $display->setOption('cache', ['type' => 'tag']);
    $display->setOption('pager', [
      'type' => 'full',
      'options' => ['items_per_page' => 50],
    ]);
    $display->setOption('style', [
      'type' => 'table',
      'options' => [
        'default' => 'deleted',
        'sticky' => TRUE,
        'empty_table' => TRUE,
      ],
    ]);
    $display->setOption('row', ['type' => 'fields']);
    $display->setOption('exposed_form', [
      'type' => 'basic',
      'options' => [
        'submit_button' => (string) $this->t('Filter'),
        'reset_button' => TRUE,
      ],
    ]);

    // Add handlers using the Views API - this automatically gets metadata
    // from Views data.
    $this->addFields($executable, $entity_type, $base_table);
    $this->addFilters($executable, $entity_type, $base_table);
    $this->addSorts($executable, $base_table);
    $this->addRelationships($executable, $entity_type, $base_table);

    // Empty text when no results.
    $executable->addHandler('default', 'empty', 'views', 'area_text_custom', [
      'empty' => TRUE,
      'content' => (string) $this->t('There are no deleted @label.', ['@label' => $entity_type->getPluralLabel()]),
    ]);

    // Update table style columns after fields are added.
    $this->updateTableStyle($executable, $entity_type);

    // Add the default tags so that it's easier to identify and alter the Trash
    // overview behavior.
    $executable->getDisplay()->setOption('query', [
      'type' => 'views_query',
      'options' => [
        'query_tags' => [
          'trash_views_overview',
          'trash_views_overview_default',
        ],
      ],
    ]);

    // Allow other modules to alter the trash view.
    $this->moduleHandler->invokeAll('trash_views_build', [$executable, $entity_type, $export]);

    return $executable;
  }

  /**
   * Adds field handlers to the View.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executable.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $base_table
   *   The Views base table name.
   */
  protected function addFields(ViewExecutable $view, EntityTypeInterface $entity_type, string $base_table): void {
    // Bulk operations field - only add if the config actions are available.
    $entity_type_id = $entity_type->id();
    $actions = $this->entityTypeManager->getStorage('action')->loadMultiple([
      $entity_type_id . '_restore_action',
      $entity_type_id . '_purge_action',
    ]);
    if (count($actions)) {
      // Core defines bulk_form on the base table, not the data table.
      $view->addHandler('default', 'field', $entity_type->getBaseTable(), $entity_type_id . '_bulk_form', [
        'label' => (string) $this->t('Bulk operations'),
        'include_exclude' => 'include',
        'selected_actions' => array_keys($actions),
      ]);
    }

    // Label field.
    $label_key = $entity_type->getKey('label');
    if ($label_key) {
      $view->addHandler('default', 'field', $base_table, $label_key, [
        'label' => (string) $this->t('Title'),
        'type' => 'trash_label',
        'settings' => ['link_to_entity' => TRUE],
      ]);
    }

    // Bundle field.
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key) {
      $view->addHandler('default', 'field', $base_table, $bundle_key, [
        'label' => (string) $entity_type->getBundleLabel(),
        'type' => 'entity_reference_label',
        'settings' => ['link' => FALSE],
      ]);
    }

    // Owner field (if entity implements EntityOwnerInterface).
    if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
      $owner_key = $entity_type->getKey('owner');
      if ($owner_key) {
        $view->addHandler('default', 'field', 'users_field_data', 'name', [
          'relationship' => 'uid',
          'label' => (string) $this->t('Author'),
          'type' => 'user_name',
        ]);
      }
    }

    // Published status field (if entity implements EntityPublishedInterface).
    if ($entity_type->entityClassImplements(EntityPublishedInterface::class)) {
      $published_key = $entity_type->getKey('published');
      if ($published_key) {
        $view->addHandler('default', 'field', $base_table, $published_key, [
          'label' => (string) $this->t('Status'),
          'type' => 'boolean',
          'settings' => [
            'format' => 'custom',
            'format_custom_false' => (string) $this->t('Unpublished'),
            'format_custom_true' => (string) $this->t('Published'),
          ],
        ]);
      }
    }

    // Language field on multilingual sites.
    if ($this->languageManager->isMultilingual() && $entity_type->isTranslatable()) {
      $langcode_key = $entity_type->getKey('langcode');
      if ($langcode_key) {
        $view->addHandler('default', 'field', $base_table, $langcode_key, [
          'label' => (string) $this->t('Language'),
        ]);
      }
    }

    // Deleted by field (if entity implements RevisionLogInterface).
    if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
      $revision_table = $entity_type->getRevisionTable();
      $revision_user_key = $entity_type->getRevisionMetadataKey('revision_user');
      if ($revision_table && $revision_user_key) {
        $view->addHandler('default', 'field', $revision_table, $revision_user_key, [
          'label' => (string) $this->t('Deleted by'),
        ]);
      }
    }

    // Deleted timestamp field.
    $view->addHandler('default', 'field', $base_table, 'deleted', [
      'label' => (string) $this->t('Deleted'),
      'date_format' => 'short',
    ]);

    // Operations field - use standard operations for entity types with a list
    // builder, fall back to trash_operations for those without.
    $operations_field = $entity_type->hasListBuilderClass() ? 'operations' : 'trash_operations';
    $view->addHandler('default', 'field', $entity_type->getBaseTable(), $operations_field, [
      'label' => (string) $this->t('Operations'),
      'destination' => TRUE,
    ]);
  }

  /**
   * Adds filter handlers to the View.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executable.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $base_table
   *   The Views base table name.
   */
  protected function addFilters(ViewExecutable $view, EntityTypeInterface $entity_type, string $base_table): void {
    // Deleted IS NOT NULL filter - ensures we only show deleted entities.
    $view->addHandler('default', 'filter', $base_table, 'deleted', [
      'operator' => 'not empty',
    ]);

    // Exposed filter for entity label.
    $label_key = $entity_type->getKey('label');
    if ($label_key) {
      $view->addHandler('default', 'filter', $base_table, $label_key, [
        'operator' => 'contains',
        'exposed' => TRUE,
        'expose' => [
          'label' => (string) $this->t('Title'),
          'identifier' => $label_key,
        ],
      ]);
    }

    // Exposed filter for bundle.
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key) {
      $view->addHandler('default', 'filter', $base_table, $bundle_key, [
        'exposed' => TRUE,
        'expose' => [
          'label' => (string) $entity_type->getBundleLabel(),
          'identifier' => $bundle_key,
        ],
      ]);
    }

    // Exposed filter for language on multilingual sites.
    if ($this->languageManager->isMultilingual() && $entity_type->isTranslatable()) {
      $langcode_key = $entity_type->getKey('langcode');
      if ($langcode_key) {
        $view->addHandler('default', 'filter', $base_table, $langcode_key, [
          'exposed' => TRUE,
          'expose' => [
            'label' => (string) $this->t('Language'),
            'identifier' => $langcode_key,
          ],
        ]);
      }
    }
  }

  /**
   * Adds sort handlers to the View.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executable.
   * @param string $base_table
   *   The Views base table name.
   */
  protected function addSorts(ViewExecutable $view, string $base_table): void {
    // Default sort by deleted date, descending.
    $view->addHandler('default', 'sort', $base_table, 'deleted', [
      'order' => 'DESC',
    ]);
  }

  /**
   * Adds relationship handlers to the View.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executable.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $base_table
   *   The Views base table name.
   */
  protected function addRelationships(ViewExecutable $view, EntityTypeInterface $entity_type, string $base_table): void {
    // Add owner relationship if entity has an owner.
    if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
      $owner_key = $entity_type->getKey('owner');
      if ($owner_key) {
        $view->addHandler('default', 'relationship', $base_table, $owner_key, [], 'uid');
      }
    }
  }

  /**
   * Gets the base table for Views from an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string
   *   The table name to use as the Views base table.
   */
  protected function getBaseTable(EntityTypeInterface $entity_type): string {
    return $entity_type->getDataTable() ?: $entity_type->getBaseTable();
  }

  /**
   * Updates the table style configuration after fields have been added.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executable.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   */
  protected function updateTableStyle(ViewExecutable $view, EntityTypeInterface $entity_type): void {
    $display = $view->getDisplay();
    $fields = $display->getOption('fields') ?: [];
    $style = $display->getOption('style');

    $columns = [];
    $info = [];

    foreach (array_keys($fields) as $field_id) {
      $columns[$field_id] = $field_id;
      $info[$field_id] = [
        'sortable' => !in_array($field_id, ['operations', $entity_type->id() . '_bulk_form'], TRUE),
        'default_sort_order' => $field_id === 'deleted' ? 'desc' : 'asc',
      ];
    }

    $style['options']['columns'] = $columns;
    $style['options']['info'] = $info;
    $display->setOption('style', $style);
  }

}
