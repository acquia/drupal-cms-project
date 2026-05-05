<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\ToolStartedExecutionInterface;

/**
 * The tool started processing status update item.
 */
class ToolStartedExecution extends ToolSelected implements ToolStartedExecutionInterface {

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::ToolStarted;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $array = parent::toArray();
    return $array + [
      'type' => $this->getType()->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): ToolStartedExecution {
    return new self(
      time: $data['time'],
      agent_id: $data['agent_id'],
      agent_name: $data['agent_name'],
      agent_runner_id: $data['agent_runner_id'] ?? NULL,
      tool_name: $data['tool_name'],
      tool_input: $data['tool_input'],
      calling_agent_id: $data['calling_agent_id'] ?? NULL,
      tool_id: $data['tool_id'] ?? '',
      tool_feedback_message: $data['tool_feedback_message'] ?? '',
    );
  }

}
