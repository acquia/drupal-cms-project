<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\trash\Handler\TrashHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Provides the Trash manager.
 */
class TrashManager implements TrashManagerInterface {

  /**
   * One of 'active', 'inactive' or 'ignore'.
   *
   * @var string
   */
  protected $trashContext = 'active';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[AutowireServiceClosure(service: 'entity.definition_update_manager')]
    protected \Closure $entityDefinitionUpdateManager,
    #[AutowireServiceClosure(service: 'entity.last_installed_schema.repository')]
    protected \Closure $entityLastInstalledSchemaRepository,
    #[AutowireServiceClosure(service: 'entity_type.manager')]
    protected \Closure $entityTypeManager,
    #[Autowire(service: 'entity.memory_cache')]
    protected CacheTagsInvalidatorInterface $entityMemoryCache,
    #[AutowireIterator(tag: 'trash_handler', indexAttribute: 'entity_type_id')]
    protected iterable $trashHandlers = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type): bool {
    return is_subclass_of($entity_type->getStorageClass(), SqlEntityStorageInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeEnabled(EntityTypeInterface|string $entity_type, ?string $bundle = NULL): bool {
    $entity_type_id = $entity_type instanceof EntityTypeInterface ? $entity_type->id() : $entity_type;
    $enabled_entity_types = $this->configFactory->get('trash.settings')->get('enabled_entity_types') ?? [];
    if (!isset($enabled_entity_types[$entity_type_id])) {
      return FALSE;
    }
    elseif ($enabled_entity_types[$entity_type_id] === []) {
      return TRUE;
    }
    elseif ($bundle === NULL || in_array($bundle, $enabled_entity_types[$entity_type_id], TRUE)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledEntityTypes(): array {
    return array_keys($this->configFactory->get('trash.settings')->get('enabled_entity_types') ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function enableEntityType(EntityTypeInterface $entity_type): void {
    /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_schema_repository */
    $entity_schema_repository = ($this->entityLastInstalledSchemaRepository)();
    $field_storage_definitions = $entity_schema_repository->getLastInstalledFieldStorageDefinitions($entity_type->id());

    if (!$this->isEntityTypeSupported($entity_type)) {
      throw new \InvalidArgumentException("Trash integration can not be enabled for the {$entity_type->id()} entity type.");
    }

    if (isset($field_storage_definitions['deleted'])) {
      if ($field_storage_definitions['deleted']->getProvider() !== 'trash') {
        throw new \InvalidArgumentException("The {$entity_type->id()} entity type already has a 'deleted' field.");
      }
      else {
        throw new \InvalidArgumentException("Trash integration is already enabled for the {$entity_type->id()} entity type.");
      }
    }

    $storage_definition = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Deleted'))
      ->setDescription(t('Time when the item got deleted'))
      ->setInternal(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE);

    /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager */
    $entity_definition_update_manager = ($this->entityDefinitionUpdateManager)();
    $entity_definition_update_manager->installFieldStorageDefinition('deleted', $entity_type->id(), 'trash', $storage_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function disableEntityType(EntityTypeInterface $entity_type): void {
    /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_schema_repository */
    $entity_schema_repository = ($this->entityLastInstalledSchemaRepository)();
    $field_storage_definitions = $entity_schema_repository->getLastInstalledFieldStorageDefinitions($entity_type->id());

    if (isset($field_storage_definitions['deleted'])) {
      /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager */
      $entity_definition_update_manager = ($this->entityDefinitionUpdateManager)();
      $entity_definition_update_manager->uninstallFieldStorageDefinition($field_storage_definitions['deleted']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function shouldAlterQueries(): bool {
    $trash_context = $this->trashContext ?? 'active';
    return $trash_context !== 'ignore';
  }

  /**
   * {@inheritdoc}
   */
  public function getTrashContext(): string {
    return $this->trashContext ?? 'active';
  }

  /**
   * {@inheritdoc}
   */
  public function setTrashContext(string $context): static {
    assert(in_array($context, ['active', 'inactive', 'ignore'], TRUE));

    if ($this->trashContext !== $context) {
      $this->trashContext = $context;

      // Clear the static entity cache for enabled entity types.
      $cache_tags_to_invalidate = [];
      foreach ($this->getEnabledEntityTypes() as $entity_type_id) {
        $cache_tags_to_invalidate[] = 'entity.memory_cache:' . $entity_type_id;

        // For Drupal versions lower than 11.2.6, we also need to clear the
        // internal latest revision cache of the storage.
        // @see https://www.drupal.org/node/3535160
        if (version_compare(\Drupal::VERSION, '11.2.6', '<')) {
          $storage = ($this->entityTypeManager)()->getStorage($entity_type_id);
          $ref = new \ReflectionProperty($storage, 'latestRevisionIds');
          $ref->setValue($storage, []);
        }
      }
      $this->entityMemoryCache->invalidateTags($cache_tags_to_invalidate);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function executeInTrashContext($context, callable $function): mixed {
    $previous = $this->trashContext;
    $this->setTrashContext($context);

    $result = $function();

    $this->setTrashContext($previous);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler(string $entity_type_id): ?TrashHandlerInterface {
    $handlers = iterator_to_array($this->trashHandlers);
    if (isset($handlers[$entity_type_id])) {
      return $handlers[$entity_type_id];
    }

    return NULL;
  }

}
