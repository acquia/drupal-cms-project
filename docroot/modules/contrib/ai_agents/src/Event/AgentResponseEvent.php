<?php

namespace Drupal\ai_agents\Event;

/**
 * This can be used to log the responses for each loop.
 */
class AgentResponseEvent extends AgentResponseEventBase {

  // The event name.
  const EVENT_NAME = 'ai_agents.response';

}
