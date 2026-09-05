<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for AI Agent status update items.
 */
interface SystemMessageInterface extends StatusBaseInterface {

  /**
   * Get the system message.
   *
   * @return string
   *   The system message.
   */
  public function getSystemPrompt(): string;

  /**
   * Set the system message.
   *
   * @param string $system_prompt
   *   The system message.
   */
  public function setSystemPrompt(string $system_prompt): void;

}
