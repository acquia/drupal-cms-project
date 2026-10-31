# Streaming responses
AI Agents can stream their final answer back token-by-token instead of
returning it as a single, complete message. This is useful when an agent is
driving a chat interface and you want the response to appear progressively,
the same way most AI provider UIs behave.

Streaming works alongside the normal tool-calling loop: an agent can call a
tool, get the result, and then stream the final answer built from that
result. The examples below assume you are already familiar with the
[custom code integration process](using_ai_agent_in_custom_code.md).

## Enabling streaming
Streaming is requested on the `ChatInput` object before it is handed to the
AI Agent's provider. If you are calling `determineSolvability()` yourself,
set it on the agent's chat input:

```php

  $chat_input = new ChatInput([
    new ChatMessage('user', 'Your question here.'),
  ]);
  $chat_input->setStreamedOutput(TRUE);
  $yourAgent->setChatInput($chat_input);

  $solution_type = $yourAgent->determineSolvability();

```

Whether the response actually streams still depends on the configured AI
provider supporting streamed chat output. If it does not, the agent falls
back to a normal, non-streamed `ChatMessage`.

## What `solve()` returns
When `determineSolvability()` resolves to `AiAgentInterface::JOB_SOLVABLE`
with streaming enabled, `solve()` returns a
`\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface`
instead of a plain string. Iterate it to consume the response as it
streams in:

```php

  $response = $yourAgent->solve();

  if ($response instanceof StreamedChatMessageIteratorInterface) {
    foreach ($response as $streamed_message) {
      // Send $streamed_message->getText() to your client as it arrives.
    }
  }
  else {
    // Non-streamed fallback: $response is a plain string.
  }

```

## The multi-round (tool call, then stream) case
A single call to `determineSolvability()` can involve more than one round
trip to the AI provider when the agent needs to call a tool before it can
give a final answer. With streaming enabled, this works as follows:

1. The provider streams a response. If that response resolves to a tool
   call, `AiAgentEntityWrapper::postStreamingCallback()` runs once the
   stream finishes, executes the tool, and calls `determineSolvability()`
   again to get the next round going.
2. If that next round itself streams, `postStreamingCallback()` returns the
   new stream, and it is chained onto the one your code is already
   iterating. You keep consuming the same `foreach` loop without any
   extra code.
3. If the agent has run out of loops (`max_loops`) before it can produce a
   final streamed answer, `postStreamingCallback()` wraps the fallback
   message in a `ReplayedChatMessageIterator` so it still reaches you
   through the same stream, rather than the loop ending silently.

You do not need to handle these cases separately in your own code: as long
as you iterate whatever `solve()` returns, the tool-call round trip and the
final answer both arrive through the same stream.

## Events not dispatched while streaming
`ai_agents` dispatches several events from `\Drupal\ai_agents\Event` as an
agent runs. Two of them are not dispatched when streaming is enabled:

- `AgentResponseEvent`
- `AgentFinishedExecutionEvent` (except when the provider call itself
  throws, in which case it is still dispatched)

Both are only dispatched on the non-streamed completion path inside
`determineSolvability()`. When streaming, the agent's completion is
resolved through `postStreamingCallback()` instead, which does not
dispatch either event. If your code relies on these events to know when an
agent has finished, do not assume they will fire for a streamed response.
Iterating the stream to the end is the reliable signal instead.
