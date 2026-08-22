<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Plugin implementation of that can add or edit a vocabulary.
 */
#[FunctionCall(
  id: 'ai_agent:preparing_response',
  function_name: 'preparing_response',
  name: 'Preparing Response',
  description: 'This function should be run as the last tool call to notify the agent system that its ready to send a text response. This should run as one extra loop when the agent has finished all its tasks and is ready to respond to the user. IMPORTANT: always use this tools at least once.',
  group: 'information_tools',
  context_definitions: [],
)]
class PreparingResponse extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $this->setOutput('Next step: Respond to the user.');
  }

}
