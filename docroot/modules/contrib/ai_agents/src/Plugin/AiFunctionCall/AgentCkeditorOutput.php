<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;

/**
 * Plugin implementation of the list bundles function.
 */
#[FunctionCall(
  id: 'ai_agent:agent_ckeditor_output',
  function_name: 'ai_agent_agent_ckeditor_output',
  name: 'CKEditor Output',
  description: 'This method takes the output that should go to the CKEditor.',
  group: 'information_tools',
  context_definitions: [
    'html' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("HTML"),
      description: new TranslatableMarkup("The HTML output to be processed by CKEditor. Can take many HTML tags, but might filter them out later. No explanations, just the HTML."),
      required: TRUE,
    ),
  ],
)]
class AgentCkeditorOutput extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Get the HTML context.
    $html = $this->getContextValue('html');
    // Just output it.
    $this->setOutput($html);
  }

}
