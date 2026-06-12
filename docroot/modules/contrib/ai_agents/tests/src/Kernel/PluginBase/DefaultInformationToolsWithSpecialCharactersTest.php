<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Test that special characters in tool parameters don't break YAML parsing.
 *
 * @group ai_agents
 */
final class DefaultInformationToolsWithSpecialCharactersTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ai',
    'ai_agents',
    'ai_agents_tools_test',
    'key',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig('ai_agents');
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
  }

  /**
   * Test that special characters in token values don't break YAML parsing.
   */
  public function testDefaultInformationToolsWithSpecialCharactersTest(): void {
    $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create([
        'id' => 'test_special_chars_agent',
        'label' => 'Test Special Characters Agent',
        'description' => 'Agent for testing special character handling in YAML.',
        'system_prompt' => 'You are a helpful assistant.',
        'tools' => [],
        'tool_settings' => [],
        'tool_usage_limits' => [],
        'orchestration_agent' => FALSE,
        'triage_agent' => FALSE,
        'max_loops' => 3,
        'default_information_tools' => "dummy_tool:\n  label: Dummy Tool\n  tool: 'ai_agents_tools_test:dummy_tool'\n  parameters:\n    input: '[ai_agents_tools_test:value]'\n",
        'masquerade_roles' => [],
        'exclude_users_role' => FALSE,
      ])->save();

    $agent = $this->container
      ->get('plugin.manager.ai_agents')
      ->createInstance('test_special_chars_agent');
    $agent->setRunnerId('test-runner-id');

    // Set a string with various special characters that can break YAML parsing.
    $special_chars_parts = [
      "John's ",
      '"weird: value" ',
      '#comment - list ',
      '{json: true} ',
      '[1,2] ',
      '&ref ',
      '*alias ',
      '!tag ',
      '|block ',
      '>fold ',
      '%directive ',
      '@reserved ',
      '`backtick ',
      '?question',
    ];
    $special_chars_string = implode('', $special_chars_parts);
    $agent->setTokenContexts(['ai_agents_tools_test' => $special_chars_string]);

    try {
      $default_tools = $agent->getDefaultInformationTools();
    }
    catch (ParseException $e) {
      $this->fail($e->getMessage());
    }

    $this->assertNotEmpty($default_tools);
    $this->assertStringContainsString($special_chars_string, $default_tools);
    $this->assertStringNotContainsString("[ai_agents_tools_test:value]", $default_tools);
  }

}
