<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for AI Agent status update items.
 */
interface TextGeneratedInterface extends StatusBaseInterface {

  /**
   * Get the generated text.
   *
   * @return string|null
   *   The generated text or null if not set.
   */
  public function getGeneratedText(): string;

  /**
   * Set the generated text.
   *
   * @param string|null $text
   *   The generated text to set.
   */
  public function setGeneratedText(string $text): void;

}
