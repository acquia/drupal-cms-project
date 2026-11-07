<?php

declare(strict_types=1);

namespace Drupal\trash\PathAlias;

use Drupal\trash\TrashManagerInterface;
use Drupal\workspaces\WorkspacesAliasRepository;

/**
 * Extends the workspaces alias repository to filter out deleted aliases.
 */
class TrashWorkspacesAliasRepository extends WorkspacesAliasRepository {

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * Sets the trash manager.
   *
   * @param \Drupal\trash\TrashManagerInterface $trashManager
   *   The trash manager service.
   *
   * @return $this
   */
  public function setTrashManager(TrashManagerInterface $trashManager): static {
    $this->trashManager = $trashManager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseQuery() {
    $query = parent::getBaseQuery();

    // Only filter out deleted aliases if trash is enabled for path aliases.
    if ($this->trashManager->isEntityTypeEnabled('path_alias')) {
      $query->isNull('base_table.deleted');
    }

    return $query;
  }

}
