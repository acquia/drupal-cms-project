<?php

namespace Drupal\ai_agents\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Event\AgentFinishedExecutionEvent;
use Drupal\ai_agents\Event\AgentRequestEvent;
use Drupal\ai_agents\Event\AgentResponseEvent;
use Drupal\ai_agents\Event\AgentStartedExecutionEvent;
use Drupal\ai_agents\Event\AgentStatusBaseInterface;
use Drupal\ai_agents\Event\AgentToolFinishedExecutionEvent;
use Drupal\ai_agents\Event\AgentToolPreExecuteEvent;
use Drupal\ai_agents\Service\AgentStatus\Storages\PrivateTempStatusStorage;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentChatHistory;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentFinishedExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentIterationExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiAgentStartedExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiProviderRequest;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\AiProviderResponse;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\SystemMessage;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\TextGenerated;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\ToolFinishedExecution;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\ToolSelected;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\ToolStartedExecution;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that listens to agent status events and stores them.
 *
 * @package Drupal\ai\EventSubscriber
 */
class AgentStatusSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\ai_agents\Service\AgentStatus\Storages\PrivateTempStatusStorage $statusStorage
   *   The status storage.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   */
  public function __construct(
    protected PrivateTempStatusStorage $statusStorage,
    protected TimeInterface $time,
    protected FunctionCallPluginManager $functionCallPluginManager,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AgentStartedExecutionEvent::EVENT_NAME => ['onAgentStartedExecution', 0],
      AgentFinishedExecutionEvent::EVENT_NAME => ['onAgentFinishedExecution', 0],
      AgentResponseEvent::EVENT_NAME => ['onAgentRespondedExecution', 0],
      AgentToolFinishedExecutionEvent::EVENT_NAME => ['onAgentToolFinishedExecution', 0],
      AgentToolPreExecuteEvent::EVENT_NAME => ['onAgentPreToolExecuteEvent', 0],
      AgentRequestEvent::EVENT_NAME => ['onAgentRequestExecution', 0],
    ];
  }

  /**
   * When an agent starts execution, we store it.
   *
   * @param \Drupal\ai_agents\Event\AgentStartedExecutionEvent $event
   *   The event.
   */
  public function onAgentStartedExecution(AgentStartedExecutionEvent $event): void {
    // We check if we should log.
    if (!$this->checkLogEvent($event)) {
      return;
    }
    // Figure out if its the actual start of the agent or a loop.
    if ($event->getLoopCount() === 0 && $this->checkLogEventType($event, AiAgentStatusItemTypes::Started)) {
      // If there is no caller id, its the root agent.
      if ($event->getCallerId() === NULL) {
        // Start the status update for this thread if it doesn't exist.
        $this->statusStorage->startStatusUpdate($event->getThreadId());
      }

      // This is the root start of the agent.
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new AiAgentStartedExecution(
        time: $this->time->getCurrentMicroTime(),
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        calling_agent_id: $event->getCallerId(),
      ));
    }
    // Then we add an iteration item.
    if ($this->checkLogEventType($event, AiAgentStatusItemTypes::Iteration)) {
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new AiAgentIterationExecution(
        time: $this->time->getCurrentMicroTime(),
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        loop_count: $event->getLoopCount(),
        calling_agent_id: $event->getCallerId(),
      ));
    }
  }

  /**
   * When an agent finishes execution, we store it.
   *
   * @param \Drupal\ai_agents\Event\AgentFinishedExecutionEvent $event
   *   The event.
   */
  public function onAgentFinishedExecution(AgentFinishedExecutionEvent $event): void {
    // We check if we should log.
    if (!$this->checkLogEvent($event)) {
      return;
    }
    if (!$this->checkLogEventType($event, AiAgentStatusItemTypes::Finished)) {
      return;
    }
    // This is the finish of the agent.
    $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new AiAgentFinishedExecution(
      time: $this->time->getCurrentMicroTime(),
      agent_id: $event->getAgentId(),
      agent_name: $event->getAgent()->getAiAgentEntity()->label(),
      agent_runner_id: $event->getAgentRunnerId(),
      calling_agent_id: $event->getCallerId(),
    ));
  }

  /**
   * When an agent requests, we store it.
   *
   * @param \Drupal\ai_agents\Event\AgentRequestEvent $event
   *   The event.
   */
  public function onAgentRequestExecution(AgentRequestEvent $event): void {
    // We check if we should log.
    if (!$this->checkLogEvent($event)) {
      return;
    }
    // Create the chat message array.
    $chat_history = [];
    foreach ($event->getChatHistory() as $message) {
      $chat_history[] = $message->toArray();
    }
    $combined_ms = $this->time->getCurrentMicroTime();
    // Set the chat message.
    if ($this->checkLogEventType($event, AiAgentStatusItemTypes::ChatHistory)) {
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new AiAgentChatHistory(
        time: $combined_ms,
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        loop_count: $event->getLoopCount(),
        chat_history: $chat_history,
        calling_agent_id: $event->getCallerId(),
      ));
    }
    // Set the system message.
    if ($this->checkLogEventType($event, AiAgentStatusItemTypes::SystemMessage)) {
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new SystemMessage(
        time: $combined_ms,
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        loop_count: $event->getLoopCount(),
        calling_agent_id: $event->getCallerId(),
        system_prompt: $event->getSystemPrompt(),
      ));
    }

    // Set the request started.
    if ($this->checkLogEventType($event, AiAgentStatusItemTypes::Request)) {
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new AiProviderRequest(
        time: $combined_ms,
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        loop_count: $event->getLoopCount(),
        request_data: $event->getChatInput()->toArray(),
        provider_name: $event->getAgent()->getAiProvider()->getPluginId(),
        model_name: $event->getAgent()->getModelName(),
        config: $event->getAgent()->getAiConfiguration(),
        calling_agent_id: $event->getCallerId(),
      ));
    }
  }

  /**
   * When an agent responds, we store it.
   *
   * @param \Drupal\ai_agents\Event\AgentResponseEvent $event
   *   The event.
   */
  public function onAgentRespondedExecution(AgentResponseEvent $event): void {
    // We check if we should log.
    if (!$this->checkLogEvent($event)) {
      return;
    }
    // Check if it selected some tools.
    $response = $event->getResponse();
    $combined_ms = $this->time->getCurrentMicroTime();
    // Report the AI provider response.
    if ($this->checkLogEventType($event, AiAgentStatusItemTypes::Response)) {
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new AiProviderResponse(
        time: $combined_ms,
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        loop_count: $event->getLoopCount(),
        response_data: $response->toArray(),
        calling_agent_id: $event->getCallerId(),
      ));
    }

    // Check if there is a text response.
    if ($response->getNormalized()->getText() !== NULL && $this->checkLogEventType($event, AiAgentStatusItemTypes::TextGenerated)) {
      $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new TextGenerated(
        time: $combined_ms,
        agent_id: $event->getAgentId(),
        agent_name: $event->getAgent()->getAiAgentEntity()->label(),
        agent_runner_id: $event->getAgentRunnerId(),
        loop_count: $event->getLoopCount(),
        text_response: $response->getNormalized()->getText(),
        calling_agent_id: $event->getCallerId(),
      ));
    }

    // Check if we should report tools.
    if (!empty($response->getNormalized()->getTools()) && $this->checkLogEventType($event, AiAgentStatusItemTypes::ToolSelected)) {
      foreach ($response->getNormalized()->getTools() as $tool) {
        // This might be null.
        if ($tool === NULL) {
          continue;
        }
        $tool_as_array = $tool->getOutputRenderArray();
        $definition = $this->functionCallPluginManager->getFunctionCallFromFunctionName($tool_as_array['function']['name']);
        $plugin = $definition->getPluginDefinition();
        $settings = $event->getAgent()->getAiAgentEntity()->get('tool_settings')[$plugin['id']]['progress_message'] ?? '';
        $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new ToolSelected(
          time: $combined_ms,
          agent_id: $event->getAgentId(),
          agent_name: $event->getAgent()->getAiAgentEntity()->label(),
          agent_runner_id: $event->getAgentRunnerId(),
          tool_name: $tool_as_array['function']['name'] ?? '',
          tool_input: $tool_as_array['function']['arguments'] ?? '',
          calling_agent_id: $event->getCallerId(),
          tool_id: $tool->getToolId() ?? '',
          tool_feedback_message: $settings,
        ));
      }
    }
  }

  /**
   * When an agent tool finishes, we store it.
   *
   * @param \Drupal\ai_agents\Event\AgentToolFinishedExecutionEvent $event
   *   The event.
   */
  public function onAgentToolFinishedExecution(AgentToolFinishedExecutionEvent $event): void {
    // We check if we should log.
    if (!$this->checkLogEvent($event)) {
      return;
    }
    // We also check if we should log this type.
    if (!$this->checkLogEventType($event, AiAgentStatusItemTypes::ToolFinished)) {
      return;
    }
    // Get the tool.
    $tool = $event->getTool();
    // Create a tool input from contexts.
    $tool_input = [];
    // Get the input values.
    $history = $event->getAgent()->getChatHistory();
    $message = end($history);
    if ($message->getTools()) {
      foreach ($message->getTools() as $tool_object) {
        if ($tool_object->getToolId() == $tool->getToolsId()) {
          $arguments = $tool_object->getArguments() ?? [];
          foreach ($arguments as $argument) {
            $tool_input[$argument->getName()] = Json::encode($argument->getValue());
          }
        }
      }
    }
    $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new ToolFinishedExecution(
      time: $this->time->getCurrentMicroTime(),
      agent_id: $event->getAgentId(),
      agent_name: $event->getAgent()->getAiAgentEntity()->label(),
      agent_runner_id: $event->getAgentRunnerId(),
      tool_name: $tool->getFunctionName(),
      tool_input: Json::encode($tool_input),
      tool_results: $tool->getReadableOutput() ?? '',
      calling_agent_id: $event->getCallerId(),
      tool_id: $tool->getToolsId() ?? '',
      tool_feedback_message: $event->getProgressMessage(),
    ));
  }

  /**
   * When an agent tool is about to execute, we store it.
   *
   * @param \Drupal\ai_agents\Event\AgentToolPreExecuteEvent $event
   *   The event.
   */
  public function onAgentPreToolExecuteEvent(AgentToolPreExecuteEvent $event): void {
    // We check if we should log.
    if (!$this->checkLogEvent($event)) {
      return;
    }
    // We also check if we should log this type.
    if (!$this->checkLogEventType($event, AiAgentStatusItemTypes::ToolStarted)) {
      return;
    }
    // Create a tool input from contexts.
    $tool_input = [];
    $tool = $event->getTool();
    // Get the input values.
    $history = $event->getAgent()->getChatHistory();
    $message = end($history);
    if ($message->getTools()) {
      foreach ($message->getTools() as $tool_object) {
        if ($tool_object->getToolId() == $tool->getToolsId()) {
          $arguments = $tool_object->getArguments() ?? [];
          foreach ($arguments as $argument) {
            $tool_input[$argument->getName()] = Json::encode($argument->getValue());
          }
        }
      }
    }
    $this->statusStorage->storeStatusUpdateItem($event->getThreadId(), new ToolStartedExecution(
      time: $this->time->getCurrentMicroTime(),
      agent_id: $event->getAgentId(),
      agent_name: $event->getAgent()->getAiAgentEntity()->label(),
      agent_runner_id: $event->getAgentRunnerId(),
      tool_name: $tool->getFunctionName(),
      tool_input: Json::encode($tool_input),
      calling_agent_id: $event->getCallerId(),
      tool_id: $tool->getToolsId() ?? '',
      tool_feedback_message: $event->getProgressMessage(),
    ));
  }

  /**
   * Check an event if we should log at all.
   *
   * @param \Drupal\ai_agents\Event\AgentStatusBaseInterface $event
   *   The event.
   */
  protected function checkLogEvent(AgentStatusBaseInterface $event): bool {
    return $event->getThreadId() !== NULL;
  }

  /**
   * Check an event if we should log that type.
   *
   * @param \Drupal\ai_agents\Event\AgentStatusBaseInterface $event
   *   The event.
   * @param \Drupal\ai_agents\Enum\AiAgentStatusItemTypes $type
   *   The type.
   */
  protected function checkLogEventType(AgentStatusBaseInterface $event, AiAgentStatusItemTypes $type): bool {
    $detailed_tracking = $event->getAgent()->getDetailedProgressTracking();
    // If its empty, we log everything.
    if (empty($detailed_tracking)) {
      return TRUE;
    }
    return in_array($type, $detailed_tracking, TRUE);
  }

}
