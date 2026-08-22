<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\SystemMessageInterface;

/**
 * The system message is set.
 */
class SystemMessage extends StatusBase implements SystemMessageInterface {

  /**
   * The current loop count of the agent.
   *
   * @var int
   */
  protected int $loopCount;

  /**
   * The system prompt message.
   *
   * @var string
   */
  protected string $systemPrompt;

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
   * @param string $system_prompt
   *   The system prompt message.
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    int $loop_count,
    string $system_prompt,
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
    $this->loopCount = $loop_count;
    $this->systemPrompt = $system_prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::SystemMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function getSystemPrompt(): string {
    return $this->systemPrompt;
  }

  /**
   * {@inheritdoc}
   */
  public function setSystemPrompt(string $system_prompt): void {
    $this->systemPrompt = $system_prompt;
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
      'system_prompt' => $this->systemPrompt,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): SystemMessage {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      loop_count: $data['loop_count'],
      system_prompt: $data['system_prompt'] ?? '',
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
    );
  }

}
