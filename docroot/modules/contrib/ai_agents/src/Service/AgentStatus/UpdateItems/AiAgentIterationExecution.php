<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\AgentIterationExecutionInterface;

/**
 * The agent is starting another iteration.
 */
class AiAgentIterationExecution extends StatusBase implements AgentIterationExecutionInterface {

  /**
   * The current loop count of the agent.
   *
   * @var int
   */
  protected int $loopCount;

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
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    int $loop_count,
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
    $this->loopCount = $loop_count;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::Iteration;
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): AiAgentIterationExecution {
    return new self($data['time'], $data['agent_id'], $data['agent_name'], $data['agent_runner_id'], $data['loop_count'], $data['calling_agent_id'] ?? NULL);
  }

}
