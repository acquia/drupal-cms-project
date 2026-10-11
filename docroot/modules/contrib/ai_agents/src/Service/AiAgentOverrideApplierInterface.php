<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Service;

use Drupal\ai_agents\AiAgentInterface;

/**
 * Defines the contract for applying AI agent overrides.
 */
interface AiAgentOverrideApplierInterface {

  /**
   * Applies overrides to the provided agent configuration.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $agent
   *   The base agent configuration entity.
   *
   * @return \Drupal\ai_agents\AiAgentInterface
   *   The agent with overrides applied.
   */
  public function applyOverrides(AiAgentInterface $agent): AiAgentInterface;

}
