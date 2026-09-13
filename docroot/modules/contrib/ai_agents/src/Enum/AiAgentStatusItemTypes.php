<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Enum;

/**
 * Enum of AI agents status types.
 */
enum AiAgentStatusItemTypes: string {
  // The agent status types.
  case Started = 'agent_started';
  case Finished = 'agent_finished';
  case Iteration = 'agent_iteration';
  case Response = 'agent_response';
  case Request = 'agent_request';
  case ChatHistory = 'agent_chat_history';

  // The provider information.
  case ProviderRequest = 'ai_provider_request';
  case ProviderResponse = 'ai_provider_response';

  // The other agent status types.
  case TextGenerated = 'text_generated';
  case SystemMessage = 'system_message';

  // The tool status types.
  case ToolSelected = 'tool_selected';
  case ToolStarted = 'tool_started';
  case ToolFinished = 'tool_finished';

  /**
   * Get a title for the capability.
   *
   * @return string
   *   The title.
   */
  public function getTitle(): string {
    return match ($this) {
      self::Finished => 'Agent Finished',
      self::Started => 'Agent Started',
      self::ToolSelected => 'Tool Selected',
      self::ToolStarted => 'Tool Started',
      self::ToolFinished => 'Tool Finished',
      self::Iteration => 'Agent Iterated',
      self::Response => 'Agent Responded',
      self::Request => 'Agent Requested',
      self::ChatHistory => 'Agent Chat History',
      self::ProviderRequest => 'AI Provider Request',
      self::ProviderResponse => 'AI Provider Response',
      self::TextGenerated => 'Text Generated',
      self::SystemMessage => 'System Message',
    };
  }

}
