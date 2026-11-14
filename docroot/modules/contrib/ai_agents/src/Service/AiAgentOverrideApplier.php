<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Service;

use Drupal\ai_agents\AiAgentInterface;
use Drupal\ai_agents\AiAgentOverrideInterface;
use Drupal\ai_agents\Entity\AiAgentOverride;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Applies AI agent overrides to runtime agent configurations.
 */
final class AiAgentOverrideApplier implements AiAgentOverrideApplierInterface {

  /**
   * Constructs the override applier.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function applyOverrides(AiAgentInterface $agent): AiAgentInterface {
    $overrides = $this->loadOverrides($agent->id());
    if ($overrides === []) {
      return $agent;
    }

    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $cloned = $storage->create($agent->toArray());
    $cloned->set('id', $agent->id());

    foreach ($overrides as $override) {
      $this->applyOverride($cloned, $override);
    }

    return $cloned;
  }

  /**
   * Loads and sorts overrides for a parent agent.
   *
   * @param string $parentId
   *   The agent ID.
   *
   * @return \Drupal\ai_agents\AiAgentOverrideInterface[]
   *   The sorted overrides.
   */
  public function loadOverrides(string $parentId): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent_override');
    $overrides = $storage->loadByProperties(['parent_agent' => $parentId]);
    $overrides = array_filter(
      $overrides,
      static fn (AiAgentOverrideInterface $override): bool => $override->status(),
    );

    usort(
      $overrides,
      static function (AiAgentOverrideInterface $a, AiAgentOverrideInterface $b): int {
        $weightComparison = $a->getWeight() <=> $b->getWeight();
        if ($weightComparison !== 0) {
          return $weightComparison;
        }
        return $a->id() <=> $b->id();
      },
    );

    return $overrides;
  }

  /**
   * Applies a single override to the provided agent instance.
   *
   * This method orchestrates all override operations including tool
   * modifications, settings changes, usage limits, and prompt adjustments.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $agent
   *   The agent instance to modify.
   * @param \Drupal\ai_agents\AiAgentOverrideInterface $override
   *   The override configuration to apply.
   */
  public function applyOverride(AiAgentInterface $agent, AiAgentOverrideInterface $override): void {
    $this->applyTools($agent, $override);
    $this->applyToolSettings($agent, $override);
    $this->applyToolUsageLimits($agent, $override);
    $this->applyPromptModification($agent, $override);
  }

  /**
   * Applies tool additions and removals.
   *
   * Adds new tools specified in the override and removes tools that should be
   * disabled. When a tool is removed, its associated settings and usage limits
   * are also cleaned up.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $agent
   *   The agent instance to modify.
   * @param \Drupal\ai_agents\AiAgentOverrideInterface $override
   *   The override configuration specifying tool changes.
   */
  public function applyTools(AiAgentInterface $agent, AiAgentOverrideInterface $override): void {
    $tools = $agent->get('tools') ?? [];
    foreach ($override->getToolsToAdd() as $toolId) {
      $tools[$toolId] = $tools[$toolId] ?? TRUE;
    }

    $toolSettings = $agent->get('tool_settings') ?? [];
    $toolUsageLimits = $agent->get('tool_usage_limits') ?? [];

    foreach ($override->getToolsToRemove() as $toolId) {
      unset($tools[$toolId], $toolSettings[$toolId], $toolUsageLimits[$toolId]);
    }

    $agent->set('tools', $tools);
    $agent->set('tool_settings', $toolSettings);
    $agent->set('tool_usage_limits', $toolUsageLimits);
  }

  /**
   * Applies tool setting overrides.
   *
   * Merges tool-specific settings from the override into the agent's existing
   * configuration. Uses recursive array merge to preserve nested settings while
   * applying overrides.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $agent
   *   The agent instance to modify.
   * @param \Drupal\ai_agents\AiAgentOverrideInterface $override
   *   The override configuration containing tool settings.
   */
  public function applyToolSettings(AiAgentInterface $agent, AiAgentOverrideInterface $override): void {
    $settingsOverride = $override->getToolSettings();
    if ($settingsOverride === []) {
      return;
    }

    $existing = $agent->get('tool_settings') ?? [];
    foreach ($settingsOverride as $toolId => $settings) {
      if (!is_array($settings)) {
        continue;
      }
      $existing[$toolId] = isset($existing[$toolId])
        ? array_replace_recursive($existing[$toolId], $settings)
        : $settings;
    }

    $agent->set('tool_settings', $existing);
  }

  /**
   * Applies tool usage limit overrides.
   *
   * Merges tool-specific usage restrictions and parameter constraints from the
   * override into the agent's existing configuration. Uses recursive array
   * merge to preserve nested limit definitions while applying overrides.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $agent
   *   The agent instance to modify.
   * @param \Drupal\ai_agents\AiAgentOverrideInterface $override
   *   The override configuration containing usage limits.
   */
  public function applyToolUsageLimits(AiAgentInterface $agent, AiAgentOverrideInterface $override): void {
    $limitsOverride = $override->getToolUsageLimits();
    if ($limitsOverride === []) {
      return;
    }

    $existing = $agent->get('tool_usage_limits') ?? [];
    foreach ($limitsOverride as $toolId => $limits) {
      if (!is_array($limits)) {
        continue;
      }
      $existing[$toolId] = isset($existing[$toolId])
        ? array_replace_recursive($existing[$toolId], $limits)
        : $limits;
    }

    $agent->set('tool_usage_limits', $existing);
  }

  /**
   * Applies prompt modifications based on the override strategy.
   *
   * Modifies the agent's system prompt according to the override's selected
   * strategy: prefix (prepend text), suffix (append text), or replace (token
   * substitution or full replacement). Maintains proper spacing and formatting
   * during modifications.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $agent
   *   The agent instance to modify.
   * @param \Drupal\ai_agents\AiAgentOverrideInterface $override
   *   The override configuration containing prompt modification instructions.
   */
  public function applyPromptModification(AiAgentInterface $agent, AiAgentOverrideInterface $override): void {
    $strategy = $override->getPromptModificationStrategy();
    $extraText = $override->getPromptExtraText();
    $systemPrompt = (string) ($agent->get('system_prompt') ?? '');

    switch ($strategy) {
      case AiAgentOverride::PROMPT_MOD_PREFIX:
        $systemPrompt = $this->mergePrompt($systemPrompt, $extraText, TRUE);
        break;

      case AiAgentOverride::PROMPT_MOD_SUFFIX:
        $systemPrompt = $this->mergePrompt($systemPrompt, $extraText, FALSE);
        break;

      case AiAgentOverride::PROMPT_MOD_REPLACE:
        $token = $override->getPromptReplaceToken();
        if ($token === '') {
          $systemPrompt = $extraText;
          break;
        }

        if (str_contains($systemPrompt, $token)) {
          $systemPrompt = str_replace($token, $extraText, $systemPrompt);
        }
        break;
    }

    $agent->set('system_prompt', $systemPrompt);
  }

  /**
   * Merges prompt fragments.
   *
   * Combines two prompt text fragments without modifying whitespace.
   * Users are responsible for managing spacing in their prompt text.
   *
   * @param string $original
   *   The original prompt text.
   * @param string $addition
   *   The text to add.
   * @param bool $prependAddition
   *   TRUE to prepend the addition, FALSE to append it.
   *
   * @return string
   *   The merged prompt text.
   */
  private function mergePrompt(string $original, string $addition, bool $prependAddition): string {
    if ($addition === '') {
      return $original;
    }

    if ($original === '') {
      return $addition;
    }

    return $prependAddition ? $addition . $original : $original . $addition;
  }

}
