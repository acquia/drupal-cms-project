<?php

namespace Drupal\ai_agents\Event;

/**
 * This can be used to log the responses for each loop.
 *
 * Not dispatched when streaming is enabled. AiAgentEntityWrapper only fires
 * this event on the non-streamed ChatMessage path in determineSolvability();
 * a streamed response resolves through postStreamingCallback() instead,
 * which does not dispatch it.
 */
class AgentResponseEvent extends AgentResponseEventBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.response';

}
