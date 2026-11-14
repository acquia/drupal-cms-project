<?php

namespace Drupal\ai_agents\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Base class.
 */
abstract class AgentStatusBase extends Event implements AgentStatusBaseInterface {

  /**
   * Constructs the object.
   *
   * @param string|null $threadId
   *   (optional) The thread ID.
   * @param string|null $callerId
   *   (optional) The caller ID.
   */
  public function __construct(
    protected ?string $threadId = NULL,
    protected ?string $callerId = NULL,
  ) {
  }

  /**
   * Get the thread ID.
   *
   * @return string|null
   *   The thread ID.
   */
  public function getThreadId(): ?string {
    return $this->threadId;
  }

  /**
   * Get the caller ID.
   *
   * @return string|null
   *   The caller ID.
   */
  public function getCallerId(): ?string {
    return $this->callerId;
  }

}
