<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class ContentLoader implements \IteratorAggregate, ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * Entity types that should never be exported.
   *
   * @var list<string>
   */
  private static array $reject = [
    // Path aliases are created when the content is, and therefore should not be
    // exported.
    'path_alias',
    // Redirects don't really make sense as default content; they're a
    // consistency layer for content that has published for a while.
    'redirect',
    // This is an internal entity type used by Search API to assist in indexing
    // and has no business being exported.
    'search_api_task',
    // It is highly unlikely that anyone wants to export emails.
    'easy_email',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  #[\Override]
  public function getIterator(): \Traversable {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      // We can safely assume that internal entities shouldn't be exported
      // (content moderation states are the main example in core).
      if ($entity_type->isInternal() || in_array($id, self::$reject, TRUE)) {
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
          yield $storage->load($entity_id);
        }
      }
    }
  }

}
