<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Unit\PluginBase;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_agents\PluginBase\AiAgentEntityWrapper;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiProviderRequest;

/**
 * @coversDefaultClass \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper
 *
 * @group ai_agents
 *
 * @see https://git.drupalcode.org/project/ai_agents/-/work_items/3584132
 */
final class AiAgentEntityWrapperAiConfigurationTest extends UnitTestCase {

  /**
   * @covers ::getAiConfiguration
   */
  public function testFreshInstanceReturnsArrayAiConfiguration(): void {
    $wrapper = (new \ReflectionClass(AiAgentEntityWrapper::class))
      ->newInstanceWithoutConstructor();

    $this->assertIsArray($wrapper->getAiConfiguration());
  }

  /**
   * AiProviderRequest's array $config must accept a fresh wrapper's value.
   */
  public function testAiProviderRequestAcceptsFreshWrapperConfig(): void {
    $wrapper = (new \ReflectionClass(AiAgentEntityWrapper::class))
      ->newInstanceWithoutConstructor();

    $request = new AiProviderRequest(
      time: 0,
      agent_id: 'a',
      agent_name: 'a',
      agent_runner_id: 'a',
      loop_count: 0,
      request_data: [],
      provider_name: 'a',
      model_name: 'a',
      config: $wrapper->getAiConfiguration(),
      calling_agent_id: NULL,
    );

    // The object must be constructed without a TypeError on $config.
    $this->assertInstanceOf(AiProviderRequest::class, $request);
  }

}
