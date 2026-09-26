<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for AI Agent status update items.
 */
interface AgentIterationExecutionInterface extends StatusBaseInterface {

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

}
