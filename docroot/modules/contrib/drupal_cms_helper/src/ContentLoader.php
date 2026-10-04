<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Iterates over all exportable content entities.
 *
 * @implements \IteratorAggregate<ContentEntityInterface>
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class ContentLoader implements \IteratorAggregate, ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * I'll give you one guess what this function does.
   *
   * @phpstan-param array<int, string> $reject
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(param: 'content_export.reject')] private array $reject,
  ) {}

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getIterator(): \Traversable {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      // We can safely assume that internal entities shouldn't be exported
      // (content moderation states are the main example in core).
      if ($entity_type->isInternal() || in_array($id, $this->reject, TRUE)) {
        continue;
      }
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        $storage = $this->entityTypeManager->getStorage($id);
        $query = $storage->getQuery()->accessCheck(FALSE);
        // Ignore users 0 or 1, since they always exist with those IDs.
        if ($id === 'user') {
          $query->condition('uid', 1, '>');
        }
        foreach ($query->execute() as $entity_id) {
          $entity = $storage->load($entity_id);
          assert($entity instanceof ContentEntityInterface);
          yield $entity;
        }
      }
    }
  }

}
