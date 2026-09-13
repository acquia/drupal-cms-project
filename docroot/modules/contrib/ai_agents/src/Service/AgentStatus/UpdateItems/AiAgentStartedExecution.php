<?php

namespace Drupal\ai_agents\Service\AgentStatus\UpdateItems;

use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\AgentStartedExecutionInterface;

/**
 * The tool started processing status update item.
 */
class AiAgentStartedExecution extends StatusBase implements AgentStartedExecutionInterface {

  /**
   * {@inheritdoc}
   */
  public function getType(): AiAgentStatusItemTypes {
    return AiAgentStatusItemTypes::Started;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): AgentStartedExecutionInterface {
    return new self($data['time'], $data['agent_id'], $data['agent_name'], $data['agent_runner_id'], $data['calling_agent_id'] ?? NULL);
  }

}
