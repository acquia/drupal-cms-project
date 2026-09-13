<?php

namespace Drupal\ai_agents\Event;

/**
 * This is a logged response when a tool has executed.
 */
class AgentToolFinishedExecutionEvent extends AgentToolBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.tool_finished_executed';

}
