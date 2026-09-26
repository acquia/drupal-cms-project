<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces;

use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface;

/**
 * Defines an interface how you store data for status updates.
 */
interface AiAgentStatusStorageInterface {

  /**
   * Start a status update storage for an agent run.
   *
   * @param string $id
   *   The unique identifier of the agent run.
   *
   * @return bool
   *   TRUE if the storage was started, FALSE otherwise.
   */
  public function startStatusUpdate(string $id): bool;

  /**
   * Store a status update for an agent run.
   *
   * @param string $id
   *   The unique identifier of the agent run.
   * @param \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface $thread
   *   The status update item to store.
   */
  public function storeStatusUpdateItem(string $id, StatusBaseInterface $thread): void;

  /**
   * Get the status updates for an agent run.
   *
   * @param string $id
   *   The unique identifier of the agent run.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface|null
   *   The loaded status update interface.
   */
  public function loadStatusUpdate(string $id): ?AiAgentStatusUpdateInterface;

  /**
   * Delete the status updates for an agent run.
   *
   * @param string $id
   *   The unique identifier of the agent run.
   */
  public function deleteStatusUpdate(string $id): void;

}
