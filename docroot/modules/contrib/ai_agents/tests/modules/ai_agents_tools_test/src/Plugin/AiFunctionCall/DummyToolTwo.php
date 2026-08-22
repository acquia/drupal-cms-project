<?php

namespace Drupal\ai_agents_tools_test\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * A second test AI function call that echoes back its input string.
 */
#[FunctionCall(
  id: 'ai_agents_tools_test:dummy_tool_two',
  function_name: 'dummy_tool_two',
  name: 'Dummy Tool Two',
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
class DummyToolTwo extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $input = $this->getContextValue('input');
    $this->setOutput($input);
  }

}
