<?php

namespace Drupal\ai_agents\Event;

use Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface;

/**
 * This can be used to log the final response.
 */
class AgentStartedExecutionEvent extends AgentStatusBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.started_execution';

  /**
   * Constructs the object.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent.
   * @param string $agentId
   *   The agent id.
   * @param array $chatHistory
   *   The chat messages.
   * @param string $agentRunnerId
   *   The agent runner id.
   * @param int $loopCount
   *   The loop count.
   * @param string|null $threadId
   *   (optional) The thread ID.
   * @param string|null $callerId
   *   (optional) The caller ID.
   */
  public function __construct(
    protected ConfigAiAgentInterface $agent,
    protected string $agentId,
    protected array $chatHistory,
    protected string $agentRunnerId,
    protected int $loopCount,
    protected ?string $threadId = NULL,
    protected ?string $callerId = NULL,
  ) {
    parent::__construct($threadId, $callerId);
  }

  /**
   * Gets the agent.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface
   *   The agent.
   */
  public function getAgent(): ConfigAiAgentInterface {
    return $this->agent;
  }

  /**
   * Gets the agent id.
   *
   * @return string
   *   The agent id.
   */
  public function getAgentId(): string {
    return $this->agentId;
  }

  /**
   * Gets the chat history.
   *
   * @return array
   *   The chat history.
   */
  public function getChatHistory(): array {
    return $this->chatHistory;
  }

  /**
   * Gets the loop count.
   *
   * @return int
   *   The loop count.
   */
  public function getLoopCount(): int {
    return $this->loopCount;
  }

  /**
   * Gets the agent runner id.
   *
   * @return string
   *   The agent runner id.
   */
  public function getAgentRunnerId(): string {
    return $this->agentRunnerId;
  }

}
