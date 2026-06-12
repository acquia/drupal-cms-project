<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for when the response comes in.
 */
interface AiProviderResponseInterface extends StatusBaseInterface {

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
   * Get response data.
   *
   * @return array
   *   The response data.
   */
  public function getResponseData(): array;

  /**
   * Set response data.
   *
   * @param array $response_data
   *   The response data.
   */
  public function setResponseData(array $response_data): void;

}
