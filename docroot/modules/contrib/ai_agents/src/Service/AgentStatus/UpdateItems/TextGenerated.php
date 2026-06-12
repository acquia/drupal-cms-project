<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\TextGeneratedInterface;

/**
 * The agent is responding.
 */
class TextGenerated extends StatusBase implements TextGeneratedInterface {

  /**
   * The current loop count of the agent.
   *
   * @var int
   */
  protected int $loopCount;

  /**
   * The text response of the agent.
   *
   * @var string
   */
  protected string $textResponse;

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
   * @param string $text_response
   *   The text response of the agent.
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    int $loop_count,
    string $text_response,
    ?string $calling_agent_id = NULL,
  ) {
    $this->time = $time;
    $this->agentId = $agent_id;
    $this->currentAgentName = $agent_name;
    $this->agentRunnerId = $agent_runner_id;
    $this->callingAgentId = $calling_agent_id;
    $this->loopCount = $loop_count;
    $this->textResponse = $text_response;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::TextGenerated;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeneratedText(): string {
    return $this->textResponse;
  }

  /**
   * {@inheritdoc}
   */
  public function setGeneratedText(string $text_response): void {
    $this->textResponse = $text_response;
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
      'text_response' => $this->textResponse,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): TextGeneratedInterface {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      loop_count: $data['loop_count'],
      text_response: $data['text_response'] ?? '',
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
    );
  }

}
