<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\AgentIterationExecutionInterface;

/**
 * The agent is setting the chat history.
 */
class AiAgentChatHistory extends StatusBase implements AgentIterationExecutionInterface {

  /**
   * The current loop count of the agent.
   *
   * @var int
   */
  protected int $loopCount;

  /**
   * The chat history.
   *
   * @var array
   */
  protected array $chatHistory = [];

  /**
   * Constructor for the status base.
   *
   * @param float $time
   *   The microtime of the status update.
   * @param string $agent_id
   *   The id of the agent config.
   * @param string $agent_name
   *   The readable name of the agent.
   * @param string $agent_runner_id
   *   The current agent runner id or null if not set.
   * @param int $loop_count
   *   The current loop count of the agent.
   * @param array $chat_history
   *   The chat history to set.
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    int $loop_count,
    array $chat_history = [],
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
    $this->chatHistory = $chat_history;
    $this->loopCount = $loop_count;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::ChatHistory;
  }

  /**
   * Get the chat history.
   *
   * @return array
   *   The chat history.
   */
  public function getChatHistory(): array {
    return $this->chatHistory;
  }

  /**
   * Set the chat history.
   *
   * @param array $chat_history
   *   The chat history.
   */
  public function setChatHistory(array $chat_history): void {
    $this->chatHistory = $chat_history;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoopNumber(): int {
    return $this->loopCount;
  }

  /**
   * {@inheritdoc}
   */
  public function setLoopNumber(int $loop_number): void {
    $this->loopCount = $loop_number;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return parent::toArray() + [
      'loop_count' => $this->loopCount,
      'chat_history' => $this->chatHistory,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): AiAgentChatHistory {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      loop_count: $data['loop_count'],
      chat_history: $data['chat_history'] ?? [],
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
    );
  }

}
