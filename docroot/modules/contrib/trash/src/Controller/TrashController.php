<?php

declare(strict_types=1);

namespace Drupal\trash\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\trash\TrashManagerInterface;
use Drupal\trash\TrashViewBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to list deleted entities.
 */
class TrashController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    protected TrashManagerInterface $trashManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected DateFormatterInterface $dateFormatter,
    protected TrashViewBuilder $viewBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('trash.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('date.formatter'),
      $container->get(TrashViewBuilder::class),
    );
  }

  /**
   * Provides the trash listing page for any entity type.
   *
   * @param string|null $entity_type_id
   *   The ID of the entity type to render.
   *
   * @return array
   *   A render array.
   */
  public function listing(?string $entity_type_id = NULL): array|RedirectResponse {
    $enabled_entity_types = $this->trashManager->getEnabledEntityTypes();
    if (empty($enabled_entity_types)) {
      throw new NotFoundHttpException();
    }

    $default_entity_type = in_array('node', $enabled_entity_types, TRUE) ? 'node' : reset($enabled_entity_types);

    // Redirect to the main trash overview route for the default entity type.
    if ($entity_type_id === $default_entity_type) {
      return new TrustedRedirectResponse(Url::fromRoute('trash.admin_content_trash')->toString());
    }

    $entity_type_id = $entity_type_id ?: $default_entity_type;
    if (!in_array($entity_type_id, $enabled_entity_types, TRUE)) {
      throw new NotFoundHttpException();
    }

    $build = $this->render($entity_type_id);
    $build['#cache']['tags'][] = 'config:trash.settings';

    return $build;
  }

  /**
   * Builds a listing of deleted entities for the given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  protected function render(string $entity_type_id): array {
    $options = [];
    foreach ($this->trashManager->getEnabledEntityTypes() as $id) {
      $options[$id] = (string) $this->entityTypeManager()->getDefinition($id)->getLabel();
    }
    $build['entity_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $options,
      '#sort_options' => TRUE,
      '#value' => $entity_type_id,
      '#attributes' => [
        'class' => ['trash-entity-type'],
      ],
      '#access' => (bool) $this->config('trash.settings')->get('compact_overview'),
    ];

    // Use Views if possible.
    $entity_type = $this->entityTypeManager()->getDefinition($entity_type_id);
    if ($this->moduleHandler()->moduleExists('views')
      && $entity_type->hasHandlerClass('views_data')) {
      $build += $this->renderView($entity_type);
    }
    else {
      // Otherwise fall back to the table implementation.
      $build += $this->renderFallbackTable($entity_type);
    }
    $build['#attached']['library'][] = 'trash/trash.admin';

    return $build;
  }

  /**
   * Renders the trash listing using a dynamically built View.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   A render array.
   */
  protected function renderView(EntityTypeInterface $entity_type): array {
    // Check if a saved view with this ID already exists.
    $view_name = 'trash_' . $entity_type->id();
    if (Views::getView($view_name)) {
      return [
        'view' => [
          '#type' => 'view',
          '#name' => $view_name,
          '#display_id' => 'default',
          '#arguments' => [],
        ],
      ];
    }

    $executable = $this->viewBuilder->buildView($entity_type);
    $renderable = $executable->buildRenderable();

    // Use embed mode to skip contextual links, since the View isn't persisted.
    $renderable['#embed'] = TRUE;

    return [
      'view' => $renderable,
    ];
  }

  /**
   * Renders the trash listing using a fallback table implementation.
   *
   * This method is used for entity types that do not have a views_data handler.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   A render array.
   */
  protected function renderFallbackTable(EntityTypeInterface $entity_type): array {
    $header = $this->buildHeader($entity_type);
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
      '#empty' => $this->t('There are no deleted @label.', ['@label' => $entity_type->getPluralLabel()]),
      '#cache' => [
        'contexts' => $entity_type->getListCacheContexts(),
        'tags' => $entity_type->getListCacheTags(),
      ],
    ];
    foreach ($this->load($entity_type, $header) as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Loads entities of this type from storage for listing.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param array $header
   *   The array containing the header column.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entities implementing \Drupal\Core\Entity\EntityInterface
   *   indexed by their IDs.
   */
  protected function load(EntityTypeInterface $entity_type, $header): array {
    $storage = $this->entityTypeManager()->getStorage($entity_type->id());
    $entity_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->exists('deleted')
      ->tableSort($header)
      ->pager(50)
      ->execute();
    return $storage->loadMultiple($entity_ids);
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   A renderable array containing a table header column.
   */
  protected function buildHeader(EntityTypeInterface $entity_type): array {
    $row['label'] = [
      'data' => $this->t('Title'),
      'specifier' => $entity_type->getKey('label'),
      'field' => $entity_type->getKey('label'),
    ];
    $row['bundle'] = [
      'data' => $entity_type->getBundleLabel(),
      'specifier' => $entity_type->getKey('bundle'),
      'field' => $entity_type->getKey('bundle'),
    ];
    if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
      $row['owner'] = [
        'data' => $this->t('Author'),
        'specifier' => $entity_type->getKey('owner'),
        'field' => $entity_type->getKey('owner'),
      ];
    }
    if ($entity_type->entityClassImplements(EntityPublishedInterface::class)) {
      $row['published'] = [
        'data' => $this->t('Status'),
        'specifier' => $entity_type->getKey('published'),
        'field' => $entity_type->getKey('published'),
      ];
    }
    if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
      $row['revision_user'] = [
        'data' => $this->t('Deleted by'),
        'specifier' => $entity_type->getRevisionMetadataKey('revision_user'),
        'field' => $entity_type->getRevisionMetadataKey('revision_user'),
      ];
    }
    $row['deleted'] = [
      'data' => $this->t('Deleted'),
      'specifier' => 'deleted',
      'field' => 'deleted',
      'sort' => 'desc',
    ];
    $row['operations'] = $this->t('Operations');
    return $row;
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for this row of the list.
   *
   * @return array
   *   A render array structure of fields for this entity.
   */
  protected function buildRow(FieldableEntityInterface $entity): array {
    $entity_type = $entity->getEntityType();
    if ($entity_type->hasLinkTemplate('canonical')
      && $entity_type->getLinkTemplate('canonical') != $entity_type->getLinkTemplate('edit-form')
      && $entity->access('view')
    ) {
      $row['label']['data'] = [
        '#type' => 'link',
        '#title' => "{$entity->label()} ({$entity->id()})",
        '#url' => $entity->toUrl('canonical', ['query' => ['in_trash' => TRUE]]),
      ];
    }
    else {
      $row['label']['data'] = [
        '#markup' => "{$entity->label()} ({$entity->id()})",
      ];
    }

    $row['bundle'] = $this->bundleInfo->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'];

    if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
      assert($entity instanceof EntityOwnerInterface);
      $row['owner']['data'] = [
        '#theme' => 'username',
        '#account' => $entity->getOwner(),
      ];
    }

    if ($entity_type->entityClassImplements(EntityPublishedInterface::class)) {
      assert($entity instanceof EntityPublishedInterface);
      $row['published'] = $entity->isPublished() ? $this->t('published') : $this->t('not published');
    }

    if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
      assert($entity instanceof RevisionLogInterface);
      $row['revision_user']['data'] = [
        '#theme' => 'username',
        '#account' => $entity->getRevisionUser(),
      ];
    }

    $row['deleted'] = $this->dateFormatter->format($entity->get('deleted')->value, 'short');

    $list_builder = $this->entityTypeManager->hasHandler($entity_type->id(), 'list_builder')
      ? $this->entityTypeManager->getListBuilder($entity_type->id())
      : $this->entityTypeManager->createHandlerInstance(EntityListBuilder::class, $entity_type);

    $row['operations']['data'] = [
      '#type' => 'operations',
      '#links' => $list_builder->getOperations($entity) ?? [],
      // Allow links to use modals.
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];

    return $row;
  }

}
