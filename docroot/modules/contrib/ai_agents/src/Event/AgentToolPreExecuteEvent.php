<?php

namespace Drupal\ai_agents\Event;

/**
 * This is a logged response before a tool has executed.
 */
class AgentToolPreExecuteEvent extends AgentToolBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.tool_pre_executed';

}
