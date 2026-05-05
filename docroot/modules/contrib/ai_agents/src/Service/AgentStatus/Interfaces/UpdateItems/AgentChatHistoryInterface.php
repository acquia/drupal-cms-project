<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for AI Agent status update items.
 */
interface AgentChatHistoryInterface extends StatusBaseInterface {

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
   * Get the chat history.
   *
   * @return array
   *   The chat history.
   */
  public function getChatHistory(): array;

  /**
   * Set the chat history.
   *
   * @param array $chat_history
   *   The chat history to set.
   */
  public function setChatHistory(array $chat_history): void;

}
