<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\Component\Serialization\Json;
use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface;

/**
 * The general status update item.
 */
class StatusBase implements StatusBaseInterface {

  /**
   * The microtime of the status update.
   *
   * @var float
   */
  protected float $time;

  /**
   * The current runner id of the agent.
   *
   * @var string
   */
  protected string $agentRunnerId;

  /**
   * The id of the agent config.
   *
   * @var string
   */
  protected string $agentId;

  /**
   * The current agent name.
   *
   * @var string
   */
  protected string $currentAgentName;

  /**
   * The calling agent id or null if this is the root agent.
   *
   * @var string|null
   */
  protected ?string $callingAgentId = NULL;

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
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    // Just set a default type, should be overridden in child classes.
    return AiAgentStatusItemTypes::ToolStarted;
  }

  /**
   * {@inheritdoc}
   */
  public function getTime(): float {
    return $this->time;
  }

  /**
   * {@inheritdoc}
   */
  public function setTime(float $microtime): void {
    $this->time = $microtime;
  }

  /**
   * {@inheritdoc}
   */
  public function getAgentId(): string {
    return $this->agentId;
  }

  /**
   * {@inheritdoc}
   */
  public function setAgentId(string $agent_id): void {
    $this->agentId = $agent_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAgentName(): string {
    return $this->currentAgentName;
  }

  /**
   * {@inheritdoc}
   */
  public function setAgentName(string $agent_name): void {
    $this->currentAgentName = $agent_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getAgentRunnerId(): ?string {
    return $this->agentRunnerId;
  }

  /**
   * {@inheritdoc}
   */
  public function setAgentRunnerId(?string $agent_runner_id): void {
    $this->agentRunnerId = $agent_runner_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallingAgentId(): ?string {
    return $this->callingAgentId;
  }

  /**
   * {@inheritdoc}
   */
  public function setCallingAgentId(?string $calling_agent_id): void {
    $this->callingAgentId = $calling_agent_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): StatusBaseInterface {
    return new self($data['time'], $data['agent_id'], $data['agent_name'], $data['agent_runner_id'], $data['calling_agent_id'] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'agent_id' => $this->agentId,
      'agent_name' => $this->currentAgentName,
      'agent_runner_id' => $this->agentRunnerId,
      'type' => $this->getType()->value,
      'time' => $this->time,
      'calling_agent_id' => $this->callingAgentId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toJson(): string {
    return Json::encode($this->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public static function fromJson(string $json): StatusBaseInterface {
    $data = Json::decode($json);
    return self::fromArray($data);
  }

}
