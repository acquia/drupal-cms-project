<?php

namespace Drupal\ai_agents_tools_test\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Plugin implementation of a simple test AI function call.
 */
#[FunctionCall(
  id: 'ai_agents_tools_test:dummy_tool',
  function_name: 'dummy_tool',
  name: 'Dummy Tool',
  description: 'A test plugin that echoes back the input string.',
  group: 'test_tools',
  context_definitions: [
    'input' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Input"),
      description: new TranslatableMarkup("The input string to echo back."),
      required: TRUE
    ),
  ],
)]
class DummyTool extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $input = $this->getContextValue('input');
    $this->setOutput($input);
  }

}
