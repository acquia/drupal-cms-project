<?php

namespace Drupal\ai_agents\Event;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface;

/**
 * Base class.
 */
abstract class AgentToolBase extends AgentStatusBase {

  /**
   * Constructs the object.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent that is executing the tool.
   * @param string $runnerId
   *   The current runner ID.
   * @param \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $tool
   *   The tool that was executed.
   * @param string $toolId
   *   The tool ID from the chat.
   * @param string $threadId
   *   (optional) The thread ID.
   * @param string|null $callerId
   *   (optional) The caller ID.
   * @param string $progress_message
   *   (optional) The progress message to show if set.
   * @param bool $is_agent_decision
   *   Whether the tool was selected by the agent (TRUE) or is a default
   *   information tool run automatically (FALSE).
   */
  public function __construct(
    protected ConfigAiAgentInterface $agent,
    protected string $runnerId,
    protected ExecutableFunctionCallInterface $tool,
    protected string $toolId,
    protected ?string $threadId = NULL,
    protected ?string $callerId = NULL,
    protected ?string $progress_message = '',
    protected bool $is_agent_decision = TRUE,
  ) {
    parent::__construct($threadId, $callerId);
  }

  /**
   * Whether the tool was selected by the agent or run automatically.
   *
   * @return bool
   *   TRUE if the agent decided to use this tool, FALSE if it is a default
   *   information tool executed automatically before the agent runs.
   */
  public function isAgentDecision(): bool {
    return $this->is_agent_decision;
  }

  /**
   * Get the agent that is executing the tool.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface
   *   The agent.
   */
  public function getAgent(): ConfigAiAgentInterface {
    return $this->agent;
  }

  /**
   * Get the agent ID of the agent executing the tool.
   *
   * @return string
   *   The agent ID.
   */
  public function getAgentId(): string {
    return $this->agent->getAiAgentEntity()->id();
  }

  /**
   * Get thread ID.
   *
   * @return string
   *   The thread ID.
   */
  public function getThreadId(): string|null {
    return $this->threadId;
  }

  /**
   * Get the agent runner ID.
   *
   * @return string
   *   The agent runner ID.
   */
  public function getAgentRunnerId(): string {
    return $this->runnerId;
  }

  /**
   * Get the tool that was executed.
   *
   * @return \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface
   *   The tool.
   */
  public function getTool(): ExecutableFunctionCallInterface {
    return $this->tool;
  }

  /**
   * Get the tool ID from the chat.
   *
   * @return string
   *   The tool ID.
   */
  public function getToolId(): string {
    return $this->toolId;
  }

  /**
   * Get the progress message to show if set.
   *
   * @return string
   *   The progress message.
   */
  public function getProgressMessage(): string {
    return $this->progress_message ?? '';
  }

}
