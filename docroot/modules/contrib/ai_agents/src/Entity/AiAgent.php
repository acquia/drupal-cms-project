<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_agents\AiAgentInterface;

/**
 * Defines the AI Agent entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_agent",
 *   label = @Translation("AI Agent"),
 *   label_collection = @Translation("AI Agents"),
 *   label_singular = @Translation("AI Agent"),
 *   label_plural = @Translation("AI Agents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI Agent",
 *     plural = "@count AI Agents",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\ai_agents\Form\AiAgentForm",
 *       "edit" = "Drupal\ai_agents\Form\AiAgentForm",
 *     },
 *   },
 *   config_prefix = "ai_agent",
 *   admin_permission = "administer ai_agent",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "default_information_tools",
 *     "system_prompt",
 *     "secured_system_prompt",
 *     "tools",
 *     "tool_usage_limits",
 *     "tool_settings",
 *     "orchestration_agent",
 *     "triage_agent",
 *     "max_loops",
 *     "max_loops_message",
 *     "masquerade_roles",
 *     "exclude_users_role",
 *     "structured_output_enabled",
 *     "structured_output_schema",
 *     "guardrail_set",
 *     "hostname_filter_disabled",
 *   },
 * )
 */
final class AiAgent extends ConfigEntityBase implements AiAgentInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    // Normalize NULL to empty arrays for sequence-typed properties to prevent
    // TypeErrors when loading config that was saved without these values set.
    foreach (['tools', 'tool_settings', 'tool_usage_limits', 'masquerade_roles'] as $property) {
      if (!isset($values[$property]) || $values[$property] === NULL) {
        $values[$property] = [];
      }
    }
    parent::__construct($values, $entity_type);
  }

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The dynamic context tools.
   */
  protected ?string $default_information_tools = NULL;

  /**
   * The system prompt (agent instructions).
   */
  protected string $system_prompt;

  /**
   * The secured system prompt that can contain secure instructions.
   */
  protected ?string $secured_system_prompt = NULL;

  /**
   * The tools that can be used.
   */
  protected array $tools = [];

  /**
   * The tool usage limits.
   */
  protected array $tool_usage_limits = [];

  /**
   * The tool settings.
   */
  protected array $tool_settings = [];

  /**
   * Is this an orchestration agent.
   */
  protected ?bool $orchestration_agent = NULL;

  /**
   * Is this a triage agent.
   */
  protected ?bool $triage_agent = NULL;

  /**
   * The max amount of loops.
   */
  protected int $max_loops = 3;

  /**
   * The message to display when max loops is reached.
   */
  protected ?string $max_loops_message = NULL;

  /**
   * The agent masquerade roles.
   */
  protected array $masquerade_roles = [];

  /**
   * Do not use users role.
   */
  protected bool $exclude_users_role = FALSE;

  /**
   * If the structured output is enabled.
   */
  protected ?bool $structured_output_enabled = NULL;

  /**
   * The structured output schema in JSON format.
   */
  protected ?string $structured_output_schema = NULL;

  /**
   * The guardrail set.
   */
  protected ?string $guardrail_set = NULL;

  /**
   * The possibility to turn off hostname whitelisting.
   */
  protected ?bool $hostname_filter_disabled = NULL;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $tool_definitions = \Drupal::service('plugin.manager.ai.function_calls')->getDefinitions();
    foreach (array_keys($this->tools) as $tool_id) {
      if (!isset($tool_definitions[$tool_id])) {
        continue;
      }

      if (isset($tool_definitions[$tool_id]['provider'])) {
        $this->addDependency('module', $tool_definitions[$tool_id]['provider']);
      }
      foreach ($tool_definitions[$tool_id]['module_dependencies'] ?? [] as $module) {
        $this->addDependency('module', $module);
      }
    }

    return $this;
  }

}
