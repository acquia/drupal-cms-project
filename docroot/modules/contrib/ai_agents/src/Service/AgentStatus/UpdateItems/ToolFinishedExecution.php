<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\ToolFinishedExecutionInterface;

/**
 * The tool finished status update item.
 */
class ToolFinishedExecution extends StatusBase implements ToolFinishedExecutionInterface {

  /**
   * The unique ID of the tool selected.
   *
   * @var string
   */
  protected string $toolId;

  /**
   * The name of the tool selected.
   *
   * @var string
   */
  protected string $toolName;

  /**
   * The input for the tool selected as a json string.
   *
   * @var string
   */
  protected string $toolInput;

  /**
   * The tool results.
   *
   * @var string
   */
  protected string $toolResults;

  /**
   * The feedback message for the tool selected.
   *
   * @var string
   */
  protected string $toolFeedbackMessage;

  /**
   * Modified constructor to include tool name, tool input and tool results.
   *
   * @param float $time
   *   The microtime of the status update.
   * @param string $agent_id
   *   The id of the agent config.
   * @param string $agent_name
   *   The readable name of the agent.
   * @param string $agent_runner_id
   *   The current agent runner id or null if not set.
   * @param string $tool_name
   *   The name of the tool selected.
   * @param string $tool_input
   *   The input for the tool selected as a json string.
   * @param string $tool_id
   *   The unique ID of the tool selected.
   * @param string $tool_results
   *   The results from the tool execution as a json string.
   * @param string $tool_feedback_message
   *   The feedback message for the tool selected.
   * @param string|null $calling_agent_id
   *   The calling agent id in the hierarchy. This is optional and can be null.
   */
  public function __construct(
    float $time,
    string $agent_id,
    string $agent_name,
    string $agent_runner_id,
    string $tool_name,
    string $tool_input,
    string $tool_id,
    string $tool_results,
    string $tool_feedback_message = '',
    ?string $calling_agent_id = NULL,
  ) {
    parent::__construct(
      time: $time,
      agent_id: $agent_id,
      agent_name: $agent_name,
      agent_runner_id: $agent_runner_id,
      calling_agent_id: $calling_agent_id,
    );
    $this->toolResults = $tool_results;
    $this->toolName = $tool_name;
    $this->toolInput = $tool_input;
    $this->toolId = $tool_id;
    $this->toolFeedbackMessage = $tool_feedback_message;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::ToolFinished;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolName(): string {
    return $this->toolName;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolName(string $tool_name): void {
    $this->toolName = $tool_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolId(): string {
    return $this->toolId;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolId(string $tool_id): void {
    $this->toolId = $tool_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolInput(): string {
    return $this->toolInput;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolInput(string $tool_input): void {
    $this->toolInput = $tool_input;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolResults(): string {
    return $this->toolResults;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolResults(string $tool_results): void {
    $this->toolResults = $tool_results;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolFeedbackMessage(): string {
    return $this->toolFeedbackMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolFeedbackMessage(string $tool_feedback_message): void {
    $this->toolFeedbackMessage = $tool_feedback_message;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $array = parent::toArray();
    return $array + [
      'tool_name' => $this->toolName,
      'tool_input' => $this->toolInput,
      'tool_results' => $this->toolResults,
      'tool_id' => $this->toolId,
      'tool_feedback_message' => $this->toolFeedbackMessage,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): ToolFinishedExecutionInterface {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      tool_name: $data['tool_name'],
      tool_input: $data['tool_input'],
      tool_results: $data['tool_results'],
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
      tool_id: $data['tool_id'] ?? '',
      tool_feedback_message: $data['tool_feedback_message'] ?? '',
    );
  }

}
