<?php

namespace Drupal\eca\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements cache related hooks for the ECA module.
 */
class CacheHooks {

  /**
   * Constructs a new CacheHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_rebuild().
   *
   * Rebuilds the subscribed events index, so that flushing all caches heals
   * an index that went stale.
   */
  #[Hook('rebuild')]
  public function rebuild(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    $storage->rebuildSubscribedEvents();
  }

}
