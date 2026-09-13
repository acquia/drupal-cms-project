<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\AiProviderResponseInterface;

/**
 * The agent is getting a response.
 */
class AiProviderResponse extends StatusBase implements AiProviderResponseInterface {

  /**
   * The current loop count of the agent.
   *
   * @var int
   */
  protected int $loopCount;

  /**
   * The response data being received from the provider.
   *
   * @var array
   */
  protected array $responseData;

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
   * @param array $response_data
   *   The response data being received from the provider.
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    int $loop_count,
    array $response_data = [],
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
    $this->loopCount = $loop_count;
    $this->responseData = $response_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::ProviderResponse;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseData(): array {
    return $this->responseData;
  }

  /**
   * {@inheritdoc}
   */
  public function setResponseData(array $response_data): void {
    $this->responseData = $response_data;
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
      'response_data' => $this->responseData,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): AiProviderResponse {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      loop_count: $data['loop_count'],
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
      response_data: $data['response_data'] ?? [],
    );
  }

}
