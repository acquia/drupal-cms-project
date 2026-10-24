<?php

namespace Drupal\ai_agents\Service\AgentStatus\Storages;

use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\ai_agents\Service\AgentStatus\AiAgentStatusUpdate;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusStorageInterface;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusUpdateInterface;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface;

/**
 * Temporary storage for agent status updates.
 */
class PrivateTempStatusStorage implements AiAgentStatusStorageInterface {

  /**
   * The store name for the temp store.
   *
   * @var string
   */
  protected string $tempStoreName = 'ai_agents_status_updates';

  /**
   * The prefix for the temp store.
   *
   * @var string
   */
  protected string $tempStorePrefix = 'ai_agents_status_updates_';

  /**
   * Constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStore
   *   The private temp store factory.
   * @param \Drupal\Core\Session\SessionManagerInterface $sessionManager
   *   The session manager.
   */
  public function __construct(
    protected PrivateTempStoreFactory $tempStore,
    protected SessionManagerInterface $sessionManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function startStatusUpdate(string $id): bool {
    // If no session exists (e.g. CLI context), skip status tracking silently.
    // The calling context (e.g. a web controller) is responsible for starting
    // the session before invoking the agent.
    if (!$this->sessionManager->isStarted()) {
      return FALSE;
    }
    // Make sure that nothing exists yet for this UUID.
    $store = $this->tempStore->get($this->tempStoreName);
    $key = $this->getTempStoreKey($id);
    // Create a new empty status update and store it.
    $status_update = new AiAgentStatusUpdate();
    $store->set($key, $status_update->toJson());
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function storeStatusUpdateItem(string $id, StatusBaseInterface $thread): void {
    // If no session exists, status tracking was never started — skip silently.
    if (!$this->sessionManager->isStarted()) {
      return;
    }
    // Check if there is already data stored for this id, otherwise skip.
    // The store may not have been initialized (e.g. subagent without a root
    // startStatusUpdate call, or data expired). Status tracking is
    // supplementary and should never crash the agent.
    $store = $this->tempStore->get($this->tempStoreName);
    $data = $store->get($this->getTempStoreKey($id));
    if (empty($data)) {
      return;
    }
    $object = AiAgentStatusUpdate::fromJson($data);
    $object->addItem($thread);
    $store->set($this->getTempStoreKey($id), $object->toJson());
  }

  /**
   * {@inheritdoc}
   */
  public function loadStatusUpdate(string $id): ?AiAgentStatusUpdateInterface {
    $store = $this->tempStore->get($this->tempStoreName);
    $data = $store->get($this->getTempStoreKey($id));
    if (empty($data)) {
      return NULL;
    }
    return AiAgentStatusUpdate::fromJson($data);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteStatusUpdate(string $id): void {
    $store = $this->tempStore->get($this->tempStoreName);
    $store->delete($this->getTempStoreKey($id));
  }

  /**
   * Helper function to generate the temp store key.
   *
   * @param string $id
   *   The unique identifier of the agent run.
   *
   * @return string
   *   The generated temp store key.
   */
  protected function getTempStoreKey(string $id): string {
    return $this->tempStorePrefix . $id;
  }

}
