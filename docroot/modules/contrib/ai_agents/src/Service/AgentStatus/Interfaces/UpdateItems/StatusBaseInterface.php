<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;

/**
 * One interface for status updates.
 */
interface StatusBaseInterface {

  /**
   * Gets the type of the status update.
   *
   * @return \Drupal\ai_agents\Enum\AiAgentStatusItemTypes
   *   The message.
   */
  public function getType(): AiAgentStatusItemTypes;

  /**
   * Gets the microtime of the status update.
   *
   * @return float
   *   The microtime.
   */
  public function getTime(): float;

  /**
   * Sets the microtime of the status update.
   *
   * @param float $microtime
   *   The microtime.
   */
  public function setTime(float $microtime): void;

  /**
   * Gets the id of the agent config.
   *
   * @return string
   *   The agent config id or null if not set.
   */
  public function getAgentId(): string;

  /**
   * Sets the id of the agent config.
   *
   * @param string $agent_id
   *   The agent config id or null if not set.
   */
  public function setAgentId(string $agent_id): void;

  /**
   * Gets the readable name of the agent.
   *
   * @return string
   *   The agent name or null if not set.
   */
  public function getAgentName(): ?string;

  /**
   * Sets the readable name of the agent.
   *
   * @param string $agent_name
   *   The agent name or null if not set.
   */
  public function setAgentName(string $agent_name): void;

  /**
   * Get the current runner agent id.
   *
   * This is the runner id of the current process. This can be
   * different from the agent id if this agent was called by another agent.
   *
   * @return string|null
   *   The current runner agent id or null if not set.
   */
  public function getAgentRunnerId(): ?string;

  /**
   * Set the current runner agent id.
   *
   * This is the id of the agent that is currently running. This can be
   * different from the agent id if this agent was called by another agent.
   *
   * @param string|null $agent_runner_id
   *   The current runner agent id or null if not set.
   */
  public function setAgentRunnerId(?string $agent_runner_id): void;

  /**
   * Get the calling agent id.
   *
   * This is is the id of the agent that called on this to start. If this is
   * the root agent, this will be empty.
   *
   * @return string|null
   *   The calling agent id or null if this is the root agent.
   */
  public function getCallingAgentId(): ?string;

  /**
   * Set the calling agent id.
   *
   * This is is the id of the agent that called on this to start. If this is
   * the root agent, this will be empty.
   *
   * @param string|null $calling_agent_id
   *   The calling agent id or null if this is the root agent.
   */
  public function setCallingAgentId(?string $calling_agent_id): void;

  /**
   * To array.
   *
   * This is to make sure its stringable for logging.
   *
   * @return array
   *   The array representation of the status update item.
   */
  public function toArray(): array;

  /**
   * From array.
   *
   * @param array $data
   *   The data to create the status update item from.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface
   *   The status update item.
   */
  public static function fromArray(array $data): StatusBaseInterface;

  /**
   * To Json.
   *
   * This is to make sure its json serializable for logging.
   *
   * @return string
   *   The json representation of the status update item.
   */
  public function toJson(): string;

  /**
   * From Json.
   *
   * @param string $json
   *   The json to create the status update item from.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface
   *   The status update item.
   */
  public static function fromJson(string $json): StatusBaseInterface;

}
