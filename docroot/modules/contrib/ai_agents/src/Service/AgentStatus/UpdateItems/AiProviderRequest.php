<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\AiProviderRequestInterface;

/**
 * The agent is starting the request.
 */
class AiProviderRequest extends StatusBase implements AiProviderRequestInterface {

  /**
   * The current loop count of the agent.
   *
   * @var int
   */
  protected int $loopCount;

  /**
   * The name of the AI provider.
   *
   * @var string
   */
  protected string $providerName;

  /**
   * The name of the model being used.
   *
   * @var string
   */
  protected string $modelName;

  /**
   * The configuration array for the provider request.
   *
   * @var array
   */
  protected array $config;

  /**
   * The request data being sent to the provider.
   *
   * @var array
   */
  protected array $requestData;

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
   * @param array $request_data
   *   The request data being sent to the provider.
   * @param string $provider_name
   *   The name of the AI provider.
   * @param string $model_name
   *   The name of the model being used.
   * @param array $config
   *   The configuration array for the provider request.
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    int $loop_count,
    array $request_data = [],
    string $provider_name = '',
    string $model_name = '',
    array $config = [],
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
    $this->loopCount = $loop_count;
    $this->providerName = $provider_name;
    $this->modelName = $model_name;
    $this->config = $config;
    $this->requestData = $request_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::ProviderRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestData(): array {
    return $this->requestData;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestData(array $request_data): void {
    $this->requestData = $request_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderName(): string {
    return $this->providerName;
  }

  /**
   * {@inheritdoc}
   */
  public function setProviderName(string $provider_name): void {
    $this->providerName = $provider_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelName(): string {
    return $this->modelName;
  }

  /**
   * {@inheritdoc}
   */
  public function setModelName(string $model_name): void {
    $this->modelName = $model_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelConfig(): array {
    return $this->config;
  }

  /**
   * {@inheritdoc}
   */
  public function setModelConfig(array $config): void {
    $this->config = $config;
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
      'request_data' => $this->requestData,
      'provider_name' => $this->providerName,
      'model_name' => $this->modelName,
      'config' => $this->config,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): AiProviderRequest {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      loop_count: $data['loop_count'],
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
      request_data: $data['request_data'] ?? [],
      provider_name: $data['provider_name'] ?? '',
      model_name: $data['model_name'] ?? '',
      config: $data['config'] ?? [],
    );
  }

}
