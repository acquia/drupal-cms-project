<?php

declare(strict_types=1);

namespace Drupal\ai_agents;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for AI agent overrides.
 */
interface AiAgentOverrideInterface extends ConfigEntityInterface {

  /**
   * Gets the parent agent ID.
   */
  public function getParentAgent(): string;

  /**
   * Gets the tool plugin IDs that should be added.
   *
   * @return string[]
   *   The tool plugin IDs.
   */
  public function getToolsToAdd(): array;

  /**
   * Gets the tool plugin IDs that should be removed.
   *
   * @return string[]
   *   The tool plugin IDs.
   */
  public function getToolsToRemove(): array;

  /**
   * Gets the prompt modification strategy.
   */
  public function getPromptModificationStrategy(): string;

  /**
   * Gets the additional prompt text.
   */
  public function getPromptExtraText(): string;

  /**
   * Gets the prompt replacement token.
   */
  public function getPromptReplaceToken(): string;

  /**
   * Determines whether the override should fail when the parent is missing.
   */
  public function shouldFailIfParentMissing(): bool;

  /**
   * Gets the override weight.
   */
  public function getWeight(): int;

  /**
   * Gets tool setting overrides.
   *
   * @return array
   *   The keyed array of tool settings indexed by plugin ID.
   */
  public function getToolSettings(): array;

  /**
   * Gets tool usage limit overrides.
   *
   * @return array
   *   The keyed array of usage limits indexed by plugin ID.
   */
  public function getToolUsageLimits(): array;

}
