<?php

declare(strict_types=1);

namespace Drupal\trash\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\trash\TrashManagerInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Entity hook implementations for Trash.
 */
class TrashEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected TrashManagerInterface $trashManager,
    protected ?WorkspaceManagerInterface $workspaceManager = NULL,
    protected ?WorkspaceInformationInterface $workspaceInformation = NULL,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['user.permissions']);
    $cacheability->addCacheableDependency($entity);

    if (trash_entity_is_deleted($entity)) {
      // Check if users can view, restore or purge deleted entities.
      if (in_array($operation, ['view', 'view label']) && $account->hasPermission('view deleted entities')) {
        return AccessResult::allowed()->addCacheableDependency($cacheability);
      }
      elseif ($operation === 'restore' && $account->hasPermission('restore ' . $entity->getEntityTypeId() . ' entities')) {
        return AccessResult::allowed()->addCacheableDependency($cacheability);
      }
      elseif ($operation === 'purge' && $account->hasPermission('purge ' . $entity->getEntityTypeId() . ' entities')) {
        // Ensure that trashed entities can only be purged in the workspace they
        // were created in or in Live.
        if ($this->workspaceInformation?->isEntitySupported($entity)
          && ($active_workspace = $this->workspaceManager?->getActiveWorkspace())
          && !$this->workspaceInformation?->isEntityDeletable($entity, $active_workspace)
        ) {
          $cacheability->addCacheableDependency($active_workspace);
          return AccessResult::forbidden()->addCacheableDependency($cacheability);
        }

        return AccessResult::allowed()->addCacheableDependency($cacheability);
      }
      else {
        return AccessResult::forbidden()->addCacheableDependency($cacheability);
      }
    }

    // If the entity is not deleted, the 'restore' and 'purge' operations should
    // not be allowed.
    return AccessResult::forbiddenIf($operation === 'restore' || $operation === 'purge')
      ->addCacheableDependency($cacheability);
  }

  /**
   * Implements hook_entity_query_alter().
   */
  #[Hook('entity_query_alter')]
  public function entityQueryAlter(QueryInterface $query): void {
    if (!$this->trashManager->shouldAlterQueries() || !$this->trashManager->isEntityTypeEnabled($query->getEntityTypeId())) {
      return;
    }

    $reflected_condition = new \ReflectionProperty($query::class, 'condition');
    $condition_group = $reflected_condition->getValue($query);
    assert($condition_group instanceof ConditionInterface);

    // Skip altering queries with an explicit filter on the 'deleted' field, so
    // you can still list deleted content, if needed.
    $deleted_key = 'deleted';
    foreach ($condition_group->conditions() as $condition) {
      if ($condition['field'] === $deleted_key) {
        return;
      }
    }

    if (!$query->getMetaData('trash')) {
      $query->addMetaData('trash', $this->trashManager->getTrashContext());
    }

    // Skip altering queries if the context has been manually set to ignore.
    if ($query->getMetaData('trash') === 'ignore') {
      return;
    }

    // If the entity query conjunction is 'OR', we need to wrap the original
    // condition group as the first condition of a new AND condition group,
    // otherwise the query would always return all non-deleted entities.
    $reflected_conjunction = new \ReflectionProperty($query::class, 'conjunction');
    if ($reflected_conjunction->getValue($query) === 'OR') {
      $reflected_conjunction->setValue($query, 'AND');

      $new_condition_group = $query->andConditionGroup()
        ->condition($condition_group);
      $reflected_condition->setValue($query, $new_condition_group);
    }

    if ($query->getMetaData('trash') === 'inactive') {
      $query->exists($deleted_key);
    }
    else {
      $query->notExists($deleted_key);
    }
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    // Skip access checks for non-deleted entities.
    if (!trash_entity_is_deleted($entity)) {
      return;
    }

    // Remove all other operations for deleted entities.
    $operations = [];
    if ($entity->access('restore')) {
      $url_options['attributes']['aria-label'] = t('Restore @label', [
        '@label' => $entity->label() ?? $entity->id(),
      ]);

      $operations['restore'] = [
        'title' => t('Restore'),
        'url' => $entity->toUrl('restore')->mergeOptions($url_options),
        'weight' => 0,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
          ]),
        ],
      ];
    }
    if ($entity->access('purge')) {
      $url_options['attributes']['aria-label'] = t('Purge @label', [
        '@label' => $entity->label() ?? $entity->id(),
      ]);

      $operations['purge'] = [
        'title' => t('Purge'),
        'url' => $entity->toUrl('purge')->mergeOptions($url_options),
        'weight' => 5,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
          ]),
        ],
      ];
    }
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() === 'workspace') {
      foreach (Element::children($build['changes']['list']) as $key) {
        $tracked_entity = $build['changes']['list'][$key]['#entity'];
        if ($this->trashManager->isEntityTypeEnabled($tracked_entity->getEntityType(), $tracked_entity->bundle()) && trash_entity_is_deleted($tracked_entity)) {
          // Highlight deleted entities in the workspace changes page.
          $build['changes']['list'][$key]['#attributes']['style'] = 'color: #a51b00; background-color: #fcf4f2;';
        }
      }
    }

    if (trash_entity_is_deleted($entity)) {
      $build['#attributes']['class'][] = 'is-deleted';
      $build['#attached']['library'][] = 'trash/trash';
    }
  }

}
