<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for AI Agent status update items.
 */
interface ToolStartedExecutionInterface extends StatusBaseInterface {

  /**
   * Get the tool name.
   *
   * @return string
   *   The tool name.
   */
  public function getToolName(): string;

  /**
   * Set the tool name.
   *
   * @param string $tool_name
   *   The tool name.
   */
  public function setToolName(string $tool_name): void;

  /**
   * The tool id in this session.
   *
   * @return string
   *   The tool id in this session.
   */
  public function getToolId(): string;

  /**
   * Set the tool id in this session.
   *
   * @param string $tool_id
   *   The tool id in this session.
   */
  public function setToolId(string $tool_id): void;

  /**
   * Get the tool input as a json string.
   *
   * @return string
   *   The tool input as a json string.
   */
  public function getToolInput(): string;

  /**
   * Set the tool input as a json string.
   *
   * @param string $tool_input
   *   The tool input as a json string.
   */
  public function setToolInput(string $tool_input): void;

  /**
   * Get the feedback message for the tool selected.
   *
   * @return string
   *   The feedback message for the tool selected.
   */
  public function getToolFeedbackMessage(): string;

  /**
   * Set the feedback message for the tool selected.
   *
   * @param string $tool_feedback_message
   *   The feedback message for the tool selected.
   */
  public function setToolFeedbackMessage(string $tool_feedback_message): void;

}
