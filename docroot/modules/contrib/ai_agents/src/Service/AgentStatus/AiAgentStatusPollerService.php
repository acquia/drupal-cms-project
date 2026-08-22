<?php

namespace Drupal\ai_agents\Service\AgentStatus;

use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusPollerServiceInterface;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusStorageInterface;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface;

/**
 * The poller service for external status updates.
 */
class AiAgentStatusPollerService implements AiAgentStatusPollerServiceInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusStorageInterface $storage
   *   The storage plugin to use.
   */
  public function __construct(
    protected readonly AiAgentStatusStorageInterface $storage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getLatestStatusUpdates(string $uuid): AiAgentStatusUpdateInterface {
    $status_update = $this->storage->loadStatusUpdate($uuid);

    // If the storage service returned nothing, create a new empty object
    // to satisfy the return type and prevent the error.
    if (is_null($status_update)) {
      return new AiAgentStatusUpdate();
    }
    return $status_update;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteStatusUpdate(string $uuid): void {
    $this->storage->deleteStatusUpdate($uuid);
  }

}
