<?php

namespace Drupal\ai_agents\Service\AgentStatus;

use Drupal\Component\Serialization\Json;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface;

/**
 * The full object of the status update for an agent run.
 */
class AiAgentStatusUpdate implements AiAgentStatusUpdateInterface {

  /**
   * The list of status update items.
   *
   * @var \Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentStatusUpdateItemInterface[]
   */
  protected array $items = [];

  /**
   * {@inheritdoc}
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $items): void {
    $this->items = $items;
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(StatusBaseInterface $item): void {
    $this->items[] = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function clearItems(): void {
    $this->items = [];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $items = [];
    foreach ($this->items as $item) {
      $items[] = $item->toArray();
    }
    return [
      'items' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): AiAgentStatusUpdateInterface {
    $instance = new self();
    foreach ($data['items'] as $item_data) {
      $item = AiAgentStatusUpdateItemFactory::createFromArray($item_data);
      $instance->addItem($item);
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toJson(): string {
    return Json::encode($this->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public static function fromJson(string $json): AiAgentStatusUpdateInterface {
    $data = Json::decode($json);
    return self::fromArray($data);
  }

}
