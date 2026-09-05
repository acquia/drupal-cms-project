<?php

namespace Drupal\ai_agents\Event;

/**
 * This can be used to log the final response.
 *
 * Dispatched unconditionally when the chat() call itself throws. Otherwise,
 * only dispatched on the non-streamed ChatMessage completion path in
 * determineSolvability(). When streaming is enabled and the agent finishes
 * (no more tools to run), postStreamingCallback() marks the agent finished
 * without dispatching this event.
 */
class AgentFinishedExecutionEvent extends AgentResponseEventBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.finished_execution';

}
