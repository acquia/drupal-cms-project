<?php

namespace Drupal\ai_agents\Event;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;

/**
 * Wrapper for request events.
 */
class AgentRequestEvent extends AgentStatusBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.request';

  /**
   * Constructs the object.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent
   *   The agent.
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input.
   * @param string $systemPrompt
   *   The system prompt.
   * @param string $agentId
   *   The agent id.
   * @param string $instructions
   *   The instructions.
   * @param array $chatHistory
   *   The chat messages.
   * @param int $loopCount
   *   The loop count.
   * @param string $agentRunnerId
   *   The agent runner id.
   * @param string|null $threadId
   *   (optional) The thread ID.
   * @param string|null $callerId
   *   (optional) The caller ID.
   */
  public function __construct(
    protected AiAgentInterface $agent,
    protected ChatInput $input,
    protected string $systemPrompt,
    protected string $agentId,
    protected string $instructions,
    protected array $chatHistory,
    protected int $loopCount,
    protected string $agentRunnerId,
    protected ?string $threadId = NULL,
    protected ?string $callerId = NULL,
  ) {
    parent::__construct($threadId, $callerId);
  }

  /**
   * Gets the agent.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The agent.
   */
  public function getAgent(): AiAgentInterface {
    return $this->agent;
  }

  /**
   * Gets the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt(): string {
    return $this->systemPrompt;
  }

  /**
   * Gets the chat input.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatInput
   *   The chat input.
   */
  public function getChatInput(): ChatInput {
    return $this->input;
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
   * Gets the instructions.
   *
   * @return string
   *   The instructions.
   */
  public function getInstructions(): string {
    return $this->instructions;
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
