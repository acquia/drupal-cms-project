<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces;

use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface;

/**
 * Defines an interface for the list of status items.
 */
interface AiAgentStatusUpdateInterface {

  /**
   * Get the list of status update items.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface[]
   *   The list of status update items.
   */
  public function getItems(): array;

  /**
   * Set a full list of status update items.
   *
   * @param \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface[] $items
   *   The list of status update items.
   */
  public function setItems(array $items): void;

  /**
   * Add a status update item to the list.
   *
   * @param \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface $item
   *   The status update item to add.
   */
  public function addItem(StatusBaseInterface $item): void;

  /**
   * Clear all status update items.
   */
  public function clearItems(): void;

  /**
   * To array so it can be serialized.
   *
   * @return array
   *   The array representation of the status update.
   */
  public function toArray(): array;

  /**
   * Create an instance from an array.
   *
   * @param array $data
   *   The array representation of the status update.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface
   *   The status update instance.
   */
  public static function fromArray(array $data): AiAgentStatusUpdateInterface;

  /**
   * To JSON so it can be serialized.
   *
   * @return string
   *   The JSON representation of the status update.
   */
  public function toJson(): string;

  /**
   * Create an instance from JSON.
   *
   * @param string $json
   *   The JSON representation of the status update.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface
   *   The status update instance.
   */
  public static function fromJson(string $json): AiAgentStatusUpdateInterface;

}
