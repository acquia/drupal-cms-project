<?php

namespace Drupal\ai_agents\Service\AgentStatus;

use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentChatHistory;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentFinishedExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentIterationExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentResponseExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentStartedExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiProviderRequest;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiProviderResponse;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\SystemMessage;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\TextGenerated;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\ToolFinishedExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\ToolSelected;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\ToolStartedExecution;

/**
 * The factory to create status update items from arrays.
 */
class AiAgentStatusUpdateItemFactory {

  /**
   * Create a status update item from an array.
   *
   * @param array $data
   *   The array representation of the status update item.
   *
   * @return \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface
   *   The status update item instance.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the type is not recognized.
   */
  public static function createFromArray(array $data): StatusBaseInterface {
    return match ($data['type']) {
      'agent_started' => AiAgentStartedExecution::fromArray($data),
      'agent_finished' => AiAgentFinishedExecution::fromArray($data),
      'agent_iteration' => AiAgentIterationExecution::fromArray($data),
      'agent_response' => AiAgentResponseExecution::fromArray($data),
      'agent_chat_history' => AiAgentChatHistory::fromArray($data),
      'ai_provider_request' => AiProviderRequest::fromArray($data),
      'ai_provider_response' => AiProviderResponse::fromArray($data),
      'system_message' => SystemMessage::fromArray($data),
      'text_generated' => TextGenerated::fromArray($data),
      'tool_selected' => ToolSelected::fromArray($data),
      'tool_started' => ToolStartedExecution::fromArray($data),
      'tool_finished' => ToolFinishedExecution::fromArray($data),
      default => throw new \InvalidArgumentException('Unknown status item type: ' . $data['type']),
    };
  }

}
