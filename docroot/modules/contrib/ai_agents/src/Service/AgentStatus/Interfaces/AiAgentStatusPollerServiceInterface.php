<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces;

/**
 * Interface for AI agent status poller services.
 *
 * This interface defines the contract for services that poll and retrieve
 * status updates for AI agent runs from various storage backends.
 */
interface AiAgentStatusPollerServiceInterface {

  /**
   * Gets the latest status updates for the given agent run.
   *
   * @param string $uuid
   *   The unique identifier of the agent run.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface
   *   The latest status update for the specified agent run.
   */
  public function getLatestStatusUpdates(string $uuid): AiAgentStatusUpdateInterface;

  /**
   * Delete all status updates for a given agent run.
   *
   * @param string $uuid
   *   The unique identifier of the agent run.
   */
  public function deleteStatusUpdate(string $uuid): void;

}
