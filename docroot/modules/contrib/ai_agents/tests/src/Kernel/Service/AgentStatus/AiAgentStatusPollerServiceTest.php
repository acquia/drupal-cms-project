<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Service\AgentStatus;

use Drupal\Core\Session\SessionManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_agents\Service\AgentStatus\AiAgentStatusPollerService;

/**
 * Tests for the AiAgentStatusPollerServiceTest class.
 *
 * @group ai_agents
 */
final class AiAgentStatusPollerServiceTest extends KernelTestBase {

  /**
   * The status poller service.
   *
   * @var \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusPollerServiceInterface
   */
  protected $statusPollerService;

  /**
   * The status poller storage service.
   *
   * @var \Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusStorageInterface
   */
  protected $privateTempStoreService;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'ai',
    'ai_agents',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Kernel test requires mock.
    $mock = $this->createMock(SessionManagerInterface::class);
    $mock->method('isStarted')->willReturn(TRUE);
    $this->container->set('session_manager', $mock);

    $this->privateTempStoreService = $this->container->get('ai_agents.private_temp_status_storage');
    // Set the poller service with the mocked session manager.
    $this->statusPollerService = new AiAgentStatusPollerService(
      $this->privateTempStoreService,
    );
  }

  /**
   * Test to check so that the status poller starts correctly.
   */
  public function testStatusPollerStarted(): void {

    // Start a new status update.
    $this->privateTempStoreService->startStatusUpdate('test-uuid-1234');
    // Check so an error is not thrown and that an empty status exists.
    $status = $this->statusPollerService->getLatestStatusUpdates('test-uuid-1234');
    $this->assertEmpty($status->getItems());
  }

}
