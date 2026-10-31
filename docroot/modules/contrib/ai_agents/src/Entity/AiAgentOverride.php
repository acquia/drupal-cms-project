<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ai_agents\AiAgentOverrideInterface;
use Drupal\ai_agents\Exception\ParentAgentNotFoundException;

/**
 * Defines the AI Agent Override config entity.
 *
 * @ConfigEntityType(
 *   id = "ai_agent_override",
 *   label = @Translation("AI Agent Override"),
 *   label_collection = @Translation("AI Agent Overrides"),
 *   label_singular = @Translation("AI Agent Override"),
 *   label_plural = @Translation("AI Agent Overrides"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI Agent Override",
 *     plural = "@count AI Agent Overrides",
 *   ),
 *   config_prefix = "ai_agent_override",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "weight",
 *     "parent_agent",
 *     "tools_add",
 *     "tools_remove",
 *     "prompt_extra_text",
 *     "prompt_mod_strategy",
 *     "prompt_replace_token",
 *     "fail_if_parent_missing",
 *     "tool_settings",
 *     "tool_usage_limits",
 *   },
 * )
 */
final class AiAgentOverride extends ConfigEntityBase implements AiAgentOverrideInterface {

  public const PROMPT_MOD_PREFIX = 'prefix';

  public const PROMPT_MOD_SUFFIX = 'suffix';

  public const PROMPT_MOD_REPLACE = 'replace';

  /**
   * The parent agent ID.
   */
  protected string $parent_agent = '';

  /**
   * The tools to add.
   *
   * @var string[]
   */
  protected array $tools_add = [];

  /**
   * The tools to remove.
   *
   * @var string[]
   */
  protected array $tools_remove = [];

  /**
   * Additional prompt text.
   */
  protected string $prompt_extra_text = '';

  /**
   * Prompt modification strategy.
   */
  protected string $prompt_mod_strategy = self::PROMPT_MOD_SUFFIX;

  /**
   * Prompt replacement token when using the replace strategy.
   */
  protected string $prompt_replace_token = '';

  /**
   * Whether to fail if the parent is missing.
   */
  protected bool $fail_if_parent_missing = FALSE;

  /**
   * The weight of this override in processing order.
   *
   * @var int
   */
  protected int $weight = 0;

  /**
   * Tool settings overrides keyed by tool plugin ID.
   *
   * @var array
   */
  protected array $tool_settings = [];

  /**
   * Tool usage limit overrides keyed by tool plugin ID.
   *
   * @var array
   */
  protected array $tool_usage_limits = [];

  /**
   * {@inheritdoc}
   */
  public function getParentAgent(): string {
    return $this->parent_agent;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolsToAdd(): array {
    return array_values(array_filter($this->tools_add, static fn ($tool): bool => is_string($tool) && $tool !== ''));
  }

  /**
   * {@inheritdoc}
   */
  public function getToolsToRemove(): array {
    return array_values(array_filter($this->tools_remove, static fn ($tool): bool => is_string($tool) && $tool !== ''));
  }

  /**
   * {@inheritdoc}
   */
  public function getPromptModificationStrategy(): string {
    return $this->prompt_mod_strategy;
  }

  /**
   * {@inheritdoc}
   */
  public function getPromptExtraText(): string {
    return $this->prompt_extra_text;
  }

  /**
   * {@inheritdoc}
   */
  public function getPromptReplaceToken(): string {
    return $this->prompt_replace_token;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldFailIfParentMissing(): bool {
    return $this->fail_if_parent_missing;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolSettings(): array {
    return $this->tool_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolUsageLimits(): array {
    return $this->tool_usage_limits;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if ($this->parent_agent === '') {
      throw new ParentAgentNotFoundException('The override must reference a parent agent.');
    }

    if ($this->shouldFailIfParentMissing()) {
      $parent = $this->entityTypeManager()
        ->getStorage('ai_agent')
        ->load($this->parent_agent);

      if ($parent === NULL) {
        throw new ParentAgentNotFoundException(sprintf('The parent agent "%s" does not exist.', $this->parent_agent));
      }
    }
  }

}
