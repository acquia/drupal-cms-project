<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_agents\Event\AgentRequestEvent;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests externally-driven agent execution with setLooped(FALSE).
 *
 * With looping disabled the agent makes one decision per determineSolvability()
 * call: a tool call is parked (not executed) and control returns to the caller,
 * who drives the next loop. The parked tool then runs at the top of that next
 * call.
 *
 * @group ai_agents
 * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::determineSolvability()
 * @see https://www.drupal.org/project/ai_agents/issues/3586052
 */
#[RunTestsInSeparateProcesses]
final class AiAgentExternallyDrivenLoopTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'link',
    'field_ui',
    'key',
    'ai',
    'ai_test',
    'ai_agents',
    'ai_agents_tools_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    // ai_test defines the ai_mock_provider_result entity type; with it enabled
    // echoai's dbRequestsToTest() queries that table, so its schema must exist
    // even though this test drives echoai from file-based fixtures only.
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installConfig(['ai', 'ai_agents', 'ai_test']);
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);

    // Every test drives the loop through the ai module's echoai provider, which
    // matches each hop's request to a recorded fixture under
    // ai_agents_tools_test/tests/resources/ai_test/requests/chat.
    $this->setEchoAiAsProvider();
  }

  /**
   * Makes the ai module's echoai provider the default for chat_with_tools.
   */
  private function setEchoAiAsProvider(): void {
    $this->container->get('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
  }

  /**
   * Returns a fresh agent wrapper for the given agent id.
   *
   * @param string $agent_id
   *   The agent id.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface
   *   The agent wrapper.
   */
  private function getAgentWrapper(string $agent_id) {
    return $this->container->get('plugin.manager.ai_agents')->createInstance($agent_id);
  }

  /**
   * A tool-calling response parks the tool and does not recurse; text ends it.
   */
  public function testExternallyDrivenLoopParksToolsAndTerminates(): void {
    $agent = $this->getAgentWrapper($this->createLoopTestAgent());
    // With looping disabled, this agent never executes a tool call or starts
    // the next loop on its own: each determineSolvability() call must be
    // invoked externally to advance the run.
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // Loop 1: invoke the agent.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml.
    $result = $agent->determineSolvability();
    // The loop must not fail (JOB_SOLVABLE), and must not consider the run
    // over: three scripted responses remain, so isFinished() has to stay
    // FALSE, and exactly one loop has been counted so far.
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertFalse($agent->isFinished());
    $this->assertSame(1, $agent->toArray()['looped']);
    // getData() returns the tool call(s) the agent decided on but has not run
    // yet. Per the script, loop 1 should park exactly one call, to dummy_tool.
    $parked = $agent->getData();
    $this->assertCount(1, $parked);
    $this->assertSame('dummy_tool', $parked[0]->getFunctionName());
    // Confirm the parked tool truly did not run and determineSolvability() did
    // not recurse: the loop counter is still 1 (asserted above) and chat
    // history holds just the initial user/assistant exchange, so exactly one
    // chat() call was made rather than looping internally.
    $this->assertSame(['user', 'assistant'], $this->historyRoles($agent));
    $this->assertEmpty($agent->getToolResults());

    // Loop 2: the tool call parked by loop 1 actually executes now, and its
    // result is appended to chat history as a tool message so the agent sees
    // it on the next call. The next tool call is then parked in turn.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml.
    $result = $agent->determineSolvability();
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertFalse($agent->isFinished());
    $this->assertSame(2, $agent->toArray()['looped']);
    // User (loop 1's prompt), assistant (loop 1's tool-call reply), tool (loop
    // 2's executed result), assistant (loop 2's reply parking the next call).
    $this->assertSame(['user', 'assistant', 'tool', 'assistant'], $this->historyRoles($agent));
    // The tool call parked in loop 1 (dummy_tool) must now have executed.
    $executed = $agent->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertSame('dummy_tool', $executed[0]->getFunctionName());
    $this->assertSame('alpha', $executed[0]->getReadableOutput());
    // The agent's second scripted response should contain the second scripted
    // tool call, dummy_tool_two, now parked for the next loop.
    $parked = $agent->getData();
    $this->assertCount(1, $parked);
    $this->assertSame('dummy_tool_two', $parked[0]->getFunctionName());

    // Loop 3: the second tool executes; the text-only response ends the run.
    // A finished externally-driven run still returns JOB_SOLVABLE: termination
    // is signalled through isFinished(), not a distinct job code.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-3-text-reply.yml.
    $result = $agent->determineSolvability();
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertTrue($agent->isFinished());
    $this->assertSame(3, $agent->toArray()['looped']);
    $this->assertSame('All done.', $agent->solve());
    $this->assertSame(
      ['user', 'assistant', 'tool', 'assistant', 'tool', 'assistant'],
      $this->historyRoles($agent),
    );
    $executed = $agent->getToolResults();
    $this->assertCount(2, $executed);
    $this->assertSame('beta', $executed[1]->getReadableOutput());
  }

  /**
   * Externally-driven loops that never stop calling tools halt at max_loops.
   *
   * A caller driving loops from outside must tell "the agent gave up" apart
   * from "the agent finished": at the ceiling determineSolvability() returns
   * JOB_NOT_SOLVABLE, isFinished() becomes TRUE and a terminal assistant
   * message is appended for the caller.
   */
  public function testExternallyDrivenLoopTerminatesAtMaxLoops(): void {
    $agent = $this->getAgentWrapper($this->createLoopTestAgent(['max_loops' => 2]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // Loops 1 and 2 stay within the ceiling: loop 1's request parks a tool, and
    // loop 2 runs it and parks another. Each returns without ending the run.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml.
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $agent->determineSolvability());
    $this->assertFalse($agent->isFinished());
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml.
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $agent->determineSolvability());
    $this->assertFalse($agent->isFinished());

    // Loop 3 crosses max_loops at the top of the call, before the tool parked
    // in loop 2 executes and before any chat call: the run ends as unsolvable.
    // fixture: none — the ceiling is reached before any chat() call.
    $result = $agent->determineSolvability();
    $this->assertSame(AiAgentInterface::JOB_NOT_SOLVABLE, $result);
    $this->assertTrue($agent->isFinished());
    // The tool parked in loop 2 was abandoned when loop 3 hit the ceiling, so
    // the last message is the terminal max_loops assistant message, not a
    // tool/assistant pair.
    $roles = $this->historyRoles($agent);
    $this->assertSame('assistant', end($roles));
  }

  /**
   * A single response with several tool calls parks and runs the whole batch.
   *
   * A parallel tool-calling response is one assistant turn carrying more than
   * one tool call, including the same tool twice with different arguments. With
   * looping disabled the whole batch is parked in one hop and executed together
   * on the next, keyed by tool-call id rather than deduplicated by name.
   */
  public function testExternallyDrivenLoopParksAndRunsParallelToolCalls(): void {
    $agent = $this->getAgentWrapper($this->createLoopTestAgent());
    $agent->setLooped(FALSE);
    // A distinct opening prompt keeps this test's requests separate from the
    // single-tool fixtures the other tests share.
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page with parallel tools.')]));

    // Loop 1: all three calls are parked in a single hop.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/parallel-tool-calls-loop-1-batch.yml.
    $result = $agent->determineSolvability();
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertFalse($agent->isFinished());
    $this->assertSame(1, $agent->toArray()['looped']);

    // The parked batch keeps every call, keyed by id (dummy_tool is not
    // collapsed to one entry), in the order the response listed them.
    $parked = $agent->getData();
    $this->assertCount(3, $parked);
    $this->assertSame(
      ['dummy_tool', 'dummy_tool', 'dummy_tool_two'],
      array_map(static fn ($tool): string => $tool->getFunctionName(), $parked),
    );

    // Nothing ran yet: the loop counter is still 1 (asserted above) and history
    // holds only the opening exchange, so this hop made exactly one chat() call
    // without recursing; no tool results yet.
    $this->assertSame(['user', 'assistant'], $this->historyRoles($agent));
    $this->assertEmpty($agent->getToolResults());

    // The whole batch serializes: every call is indexed in context_tools by its
    // tools_id, and each call's arguments survive in chat_history.
    $stored = $agent->toArray();
    $this->assertSame(
      [
        ['tools_id' => 'call_1', 'function_name' => 'dummy_tool'],
        ['tools_id' => 'call_2', 'function_name' => 'dummy_tool'],
        ['tools_id' => 'call_3', 'function_name' => 'dummy_tool_two'],
      ],
      $stored['context_tools'],
    );
    $tool_calls = $stored['chat_history'][1]['tools'];
    $this->assertSame(['input' => 'alpha'], Json::decode($tool_calls[0]['function']['arguments']));
    $this->assertSame(['input' => 'beta'], Json::decode($tool_calls[1]['function']['arguments']));
    $this->assertSame(['input' => 'gamma'], Json::decode($tool_calls[2]['function']['arguments']));

    // Loop 2: the whole parked batch executes; the text response ends the run.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/parallel-tool-calls-loop-2-text-reply.yml.
    $result = $agent->determineSolvability();
    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertTrue($agent->isFinished());
    $this->assertSame(2, $agent->toArray()['looped']);
    $this->assertSame('Parallel tools executed.', $agent->solve());

    // All three ran, in order, each echoing its own argument.
    $executed = $agent->getToolResults();
    $this->assertCount(3, $executed);
    $this->assertSame(
      ['alpha', 'beta', 'gamma'],
      array_map(static fn ($tool): string => $tool->getReadableOutput(), $executed),
    );

    // Each executed call appended its own tool message before the terminal
    // assistant text: user, assistant (the batch), tool, tool, tool, assistant.
    $this->assertSame(
      ['user', 'assistant', 'tool', 'tool', 'tool', 'assistant'],
      $this->historyRoles($agent),
    );
  }

  /**
   * Serialized loop state round-trips and a resumed agent continues.
   *
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::toArray()
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::fromArray()
   */
  public function testStateSerializationRoundTrip(): void {
    $agent_id = $this->createLoopTestAgent();
    $agent = $this->getAgentWrapper($agent_id);
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml.
    $agent->determineSolvability();

    // toArray() is what an externally-driven caller persists between loops.
    // Confirm it captures exactly what a resumed agent needs: the loop
    // counter, that looping is still disabled, the two chat messages from
    // loop 1 (user, assistant), and the parked tool call serialized down to
    // its tools_id and function_name (not the full tool, since it hasn't
    // executed yet).
    $storedAgentArrayAfterFirstResponse = $agent->toArray();
    $this->assertSame(1, $storedAgentArrayAfterFirstResponse['looped']);
    $this->assertFalse($storedAgentArrayAfterFirstResponse['looped_enabled']);
    $this->assertCount(2, $storedAgentArrayAfterFirstResponse['chat_history']);
    $this->assertSame(
      [['tools_id' => 'call_1', 'function_name' => 'dummy_tool']],
      $storedAgentArrayAfterFirstResponse['context_tools'],
    );
    // The tool arguments are also present in the agent array, in chat_history.
    $parked_tool_call = $storedAgentArrayAfterFirstResponse['chat_history'][1]['tools'][0];
    $this->assertSame('call_1', $parked_tool_call['id']);
    $this->assertSame(['input' => 'alpha'], Json::decode($parked_tool_call['function']['arguments']));

    // Resume into a fresh agent: serializing it again reproduces the stored
    // state exactly (loop counters, chat history and the parked tool). This is
    // the fixed point every loop relies on when it reloads from tempstore.
    $resumedAgentFromFirstResponse = $this->getAgentWrapper($agent_id);
    $resumedAgentFromFirstResponse->fromArray($storedAgentArrayAfterFirstResponse);
    $this->assertEquals($storedAgentArrayAfterFirstResponse, $resumedAgentFromFirstResponse->toArray());
    // The finished flag is not serialized, so assert the resumed run is live.
    $this->assertFalse($resumedAgentFromFirstResponse->isFinished());

    // The resumed agent continues where it left off: the parked tool runs and
    // the next queued response is parked, while the prior history is only
    // appended to, never rewritten.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml.
    $resumedAgentFromFirstResponse->determineSolvability();
    $storedAgentArrayAfterSecondResponse = $resumedAgentFromFirstResponse->toArray();
    // Loop 1 serialized 2 messages (user, assistant); this loop appends 2 more
    // (tool, assistant), growing history to 4. Comparing the first 2 of those
    // 4 against the original 2 asserts they are byte-for-byte identical, in
    // the same order: the resume only appends, it never rewrites or reorders
    // prior history.
    $this->assertSame(
      $storedAgentArrayAfterFirstResponse['chat_history'],
      array_slice($storedAgentArrayAfterSecondResponse['chat_history'], 0, 2),
    );

    // Same validation as after loop 1, but for loop 2 on the resumed agent:
    // the loop counter advanced, looping is still disabled, and the newly
    // parked tool call (dummy_tool_two/call_2) is indexed in context_tools
    // with its argument present in chat_history.
    $this->assertSame(2, $storedAgentArrayAfterSecondResponse['looped']);
    $this->assertFalse($storedAgentArrayAfterSecondResponse['looped_enabled']);
    $this->assertSame(
      [['tools_id' => 'call_2', 'function_name' => 'dummy_tool_two']],
      $storedAgentArrayAfterSecondResponse['context_tools'],
    );
    $parked_tool_call = $storedAgentArrayAfterSecondResponse['chat_history'][3]['tools'][0];
    $this->assertSame('call_2', $parked_tool_call['id']);
    $this->assertSame(['input' => 'beta'], Json::decode($parked_tool_call['function']['arguments']));

    // Get another fresh agent from tempstore on the next loop: loop 2's array
    // round-trips into a brand new instance exactly as loop 1's did above.
    $resumedAgentFromSecondResponse = $this->getAgentWrapper($agent_id);
    $resumedAgentFromSecondResponse->fromArray($storedAgentArrayAfterSecondResponse);
    $this->assertEquals($storedAgentArrayAfterSecondResponse, $resumedAgentFromSecondResponse->toArray());
    $this->assertFalse($resumedAgentFromSecondResponse->isFinished());
  }

  /**
   * With looping enabled the same script runs to completion in one call.
   */
  public function testLoopedModeRunsToCompletionInOneCall(): void {
    // Leave loopedEnabled at its TRUE default.
    $agent = $this->getAgentWrapper($this->createLoopTestAgent());
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // With looping enabled, this single call recurses internally through all
    // 3 loops (executing both tools) until the text response ends the run.
    // fixtures (one per internal loop, in order):
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-3-text-reply.yml.
    $agent->determineSolvability();

    $this->assertTrue($agent->isFinished());
    $this->assertSame('All done.', $agent->answerQuestion());
    $this->assertSame(
      ['user', 'assistant', 'tool', 'assistant', 'tool', 'assistant'],
      $this->historyRoles($agent),
    );
    $this->assertCount(2, $agent->getToolResults());
  }

  /**
   * A default information tool with no available_on_loop feeds every loop.
   *
   * Without available_on_loop the tool runs on each determineSolvability() call
   * and its output is routed into the system prompt handed to the provider, so
   * an externally-driven caller re-feeds it on every loop.
   */
  public function testInformationToolFeedsSystemPromptEveryLoop(): void {
    $default_information_tools = Yaml::dump([
      'info_tool' => [
        'label' => 'Info Tool',
        'tool' => 'ai_agents_tools_test:dummy_tool_three',
        'parameters' => [
          'input' => 'default-information-tool-input',
        ],
      ],
    ]);

    $agent = $this->getAgentWrapper($this->createLoopTestAgent([
      'default_information_tools' => $default_information_tools,
    ]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // Each externally-driven loop runs the info tool and feeds its output into
    // the system prompt, tagged with the live loop counter. getSystemPrompt()
    // rebuilds the exact string the loop just handed the provider: after loop
    // N the loop counter is still N (it is incremented at the top of loop N+1).
    // fixtures (one per loop iteration, in order):
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-3-text-reply.yml.
    foreach (['first', 'second', 'third'] as $marker) {
      $agent->determineSolvability();
      $system_prompt = $agent->getSystemPrompt();
      $this->assertStringContainsString('dummy_tool_three', $system_prompt);
      $this->assertStringContainsString('default-information-tool-input', $system_prompt);
      $this->assertStringContainsString("This is the $marker time", $system_prompt);
    }

    // The info tool ran on the third loop too; the text response then ended it.
    $this->assertTrue($agent->isFinished());

    // With no available_on_loop the output is system-prompt only: it never
    // lands in chat history, and so is never serialized to tempstore.
    foreach ($agent->getChatHistory() as $message) {
      $this->assertStringNotContainsString('default-information-tool-input', $message->getText());
    }

    // The info tool is context, not an agent-decided call: only the two parked
    // tools appear in the tool results.
    $tool_results = $agent->getToolResults();
    $this->assertCount(2, $tool_results);
    $this->assertSame('dummy_tool', $tool_results[0]->getFunctionName());
    $this->assertSame('dummy_tool_two', $tool_results[1]->getFunctionName());
  }

  /**
   * A default information tool with available_on_loop is added to chat history.
   *
   * Behavior under test: when a default information tool declares
   * available_on_loop, the wrapper runs it and adds its output to the chat
   * history as a user message (rather than into the system prompt), and that
   * message is carried forward on every subsequent externally-driven loop.
   *
   * Method: subscribe to the agent request event, read the chat history the
   * wrapper generated for each loop, and confirm the information tool's user
   * message is present. This checks the request the backend builds directly, so
   * it needs no provider response or recorded fixture.
   *
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::getSystemPrompt()
   */
  public function testInformationToolGatedByAvailableOnLoopFeedsChatHistoryEachLoop(): void {
    // A single default information tool, set to run on loop 1 only.
    $default_information_tools = Yaml::dump([
      'info_tool' => [
        'label' => 'Info Tool',
        'tool' => 'ai_agents_tools_test:dummy_tool_three',
        'parameters' => [
          'input' => 'default-information-tool-input',
        ],
        'available_on_loop' => [1],
      ],
    ]);

    // Subscribe to the agent request event and keep the chat input the wrapper
    // built for each loop. This fires once per loop, just before the request
    // would be sent to the provider, so its messages are the chat history the
    // backend generated for that loop.
    $requests = [];
    $this->container->get('event_dispatcher')->addListener(
      AgentRequestEvent::EVENT_NAME,
      static function (AgentRequestEvent $event) use (&$requests): void {
        $requests[] = $event->getChatInput();
      },
    );

    // The agent has no callable tools: the information tool is run through
    // default_information_tools, not the tool list, so nothing needs a
    // tool-calling response and echoai's generic text reply lets each loop end.
    $agent = $this->getAgentWrapper($this->createLoopTestAgent([
      'tools' => [],
      'tool_settings' => [],
      'default_information_tools' => $default_information_tools,
    ]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // With looping disabled the caller drives the run, so invoke three loops by
    // hand; the provider's reply does not matter, only the request each loop
    // built (captured above).
    $agent->determineSolvability();
    $agent->determineSolvability();
    $agent->determineSolvability();

    $this->assertCount(3, $requests);
    foreach ($requests as $loop => $request) {
      // Find the information tool's user message in this loop's chat history.
      // The wrapper wraps information-tool output in a message beginning with
      // this fixed preamble; matching it picks out the genuine message and
      // skips the provider's echo, which parrots earlier messages back behind
      // its own "Hello world!" prefix.
      $info_messages = array_values(array_filter(
        $request->getMessages(),
        static fn (ChatMessage $message): bool => str_starts_with($message->getText(), 'The following is information that is important as context:'),
      ));

      // The tool ran on loop 1 and its message is carried forward unchanged, so
      // every loop's chat history holds exactly one such user message.
      $this->assertCount(1, $info_messages, sprintf('Loop %d chat history holds the information message exactly once.', $loop + 1));
      $this->assertSame('user', $info_messages[0]->getRole());
      $this->assertStringContainsString('default-information-tool-input', $info_messages[0]->getText());

      // available_on_loop routes the output to chat history, so it must never
      // appear in the system prompt.
      $this->assertStringNotContainsString('default-information-tool-input', $request->getSystemPrompt());
    }
  }

  /**
   * A chat history carrying an image round-trips through the serialized state.
   *
   * ::toArray() serializes each file on a message down to an array, so
   * ::fromArray() has to turn those arrays back into file objects before any
   * later ChatMessage::toArray() call reaches them.
   *
   * @see https://www.drupal.org/project/ai_agents/issues/3586067
   */
  public function testStateSerializationRoundTripWithImage(): void {
    $agent_id = $this->createLoopTestAgent();
    $agent = $this->getAgentWrapper($agent_id);
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([
      new ChatMessage('user', 'Describe this picture.', [$this->createTestImage()]),
    ]));

    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/image-input-loop-1-tool-call.yml.
    $agent->determineSolvability();

    // The image is serialized to an array on the stored user message.
    $stored = $agent->toArray();
    $this->assertSame(
      ['type' => ImageFile::class, 'mime_type' => 'image/png', 'filename' => 'picture.png'],
      array_diff_key($stored['chat_history'][0]['images'][0], ['base64' => TRUE]),
    );

    // Resuming must rebuild that array into an ImageFile again, and serializing
    // the resumed agent must reproduce the stored state unchanged.
    $resumed = $this->getAgentWrapper($agent_id);
    $resumed->fromArray($stored);
    $this->assertEquals($stored, $resumed->toArray());
    $restored_images = $resumed->getChatHistory()[0]->getImages();
    $this->assertCount(1, $restored_images);
    $this->assertInstanceOf(ImageFile::class, $restored_images[0]);
    $this->assertSame('picture.png', $restored_images[0]->getFilename());
  }

  /**
   * Returns a one-pixel PNG as an image file to attach to a chat message.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The image file.
   */
  private function createTestImage(): ImageFile {
    $image = new ImageFile();
    $image->setFromBase64EncodedString('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC');
    $image->setMimeType('image/png');
    $image->setFilename('picture.png');
    return $image;
  }

  /**
   * Creates and saves the loop test agent config entity.
   *
   * @param array $overrides
   *   Top-level config values to override on the fixture.
   *
   * @return string
   *   The saved agent id.
   */
  private function createLoopTestAgent(array $overrides = []): string {
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.loop_test_agent.yml');
    $data = array_replace($data, $overrides);
    $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create($data)
      ->save();

    // Saving an ai_agent lazy-instantiates both plugin managers mid-save with
    // definitions that omit the new entity. Reset them so later lookups see it.
    $this->container->set('plugin.manager.ai_agents', NULL);
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();

    return $data['id'];
  }

  /**
   * Returns the ordered list of chat-history message roles.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent.
   *
   * @return string[]
   *   The roles, in order.
   */
  private function historyRoles($agent): array {
    return array_map(
      static fn (ChatMessage $message): string => $message->getRole(),
      $agent->getChatHistory(),
    );
  }

}
