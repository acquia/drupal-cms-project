<?php

namespace Drupal\ai_agents\Event;

/**
 * This can be used to log the final response.
 */
class AgentFinishedExecutionEvent extends AgentResponseEventBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.finished_execution';

}
