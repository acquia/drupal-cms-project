<?php

namespace Drupal\ai_agents\Event;

/**
 * Base class.
 */
interface AgentStatusBaseInterface {

  /**
   * Get the thread ID.
   *
   * @return string|null
   *   The thread ID.
   */
  public function getThreadId(): ?string;

  /**
   * Get the caller ID.
   *
   * @return string|null
   *   The caller ID.
   */
  public function getCallerId(): ?string;

}
