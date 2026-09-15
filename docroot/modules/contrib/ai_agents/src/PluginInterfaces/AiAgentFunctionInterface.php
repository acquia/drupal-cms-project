<?php

namespace Drupal\ai_agents\PluginInterfaces;

use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;

/**
 * AI Agent Function Interface.
 */
interface AiAgentFunctionInterface extends FunctionCallInterface {

  /**
   * Get the agent.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface
   *   The agent.
   */
  public function getAgent(): ConfigAiAgentInterface;

  /**
   * Set the agent.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent.
   */
  public function setAgent(ConfigAiAgentInterface $agent);

  /**
   * Set tokens for the agent.
   *
   * @param array $tokens
   *   The tokens to set.
   */
  public function setTokens(array $tokens);

}
