<?php

namespace Drupal\ai_agents_tools_test\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountInterface;

/**
 * Hook implementations for ai_agents_tools_test.
 */
final class AiAgentsToolsTestHooks {

  /**
   * Implements hook_entity_field_access().
   *
   * Forbids viewing and editing any field named 'field_secret' so that field
   * level access checks can be exercised on both the read and write paths in
   * tests.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if (in_array($operation, ['view', 'edit'], TRUE) && $field_definition->getName() === 'field_secret') {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'types' => [
        'ai_agents_tools_test' => [
          'name' => 'AI Agents Tools Test',
          'description' => 'Tokens for testing the AI Agents module.',
        ],
      ],
      'tokens' => [
        'ai_agents_tools_test' => [
          'value' => [
            'name' => 'Value',
            'description' => 'A configurable string value injected via setTokenContexts().',
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL): array {
    $replacements = [];
    if ($type === 'ai_agents_tools_test' && isset($data['ai_agents_tools_test'])) {
      foreach ($tokens as $name => $original) {
        if ($name === 'value') {
          $replacements[$original] = $data['ai_agents_tools_test'];
        }
      }
    }

    return $replacements;
  }

}
