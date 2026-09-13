<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for AI Agent status update items.
 */
interface AgentResponseExecutionInterface extends StatusBaseInterface {

  /**
   * Get the loop number of the iteration.
   *
   * @return int
   *   The loop number.
   */
  public function getLoopNumber(): int;

  /**
   * Set the loop number of the iteration.
   *
   * @param int $loop_number
   *   The loop number.
   */
  public function setLoopNumber(int $loop_number): void;

  /**
   * Get the textual response of the agent.
   *
   * @return string|null
   *   The response text.
   */
  public function getTextResponse(): string|null;

  /**
   * Set the textual response of the agent.
   *
   * @param string $response_text
   *   The response text.
   */
  public function setTextResponse(string $response_text): void;

}
