# Events

Observe the full lifecycle of providers, agents, tools, streams, and queued executions with Laravel events.

::: tip Events vs Middleware
**[Middleware](/features/middleware)** modifies behavior — it wraps execution and can change inputs/outputs. **Events** observe behavior — they fire at specific lifecycle points and are purely informational. Use middleware when you need to intercept or transform; use events when you need to log, broadcast, or react.
:::

## Overview

Atlas fires events across six groups. Events fire automatically — no configuration needed to enable them. Every event is a standard Laravel event, so you can listen with `Event::listen`, an `EventServiceProvider`, or Laravel's event discovery.

All events live in the `Atlasphp\Atlas\Events` namespace.

## Provider Events

Fired by the shared `HttpClient` on every API call to a provider.

<div class="full-width-table">

| Event | Properties |
|-------|-----------|
| `ProviderRequestStarted`<br><small>Before the HTTP request is sent</small> | `string $url`, `array $body` |
| `ProviderRequestCompleted`<br><small>After a successful response is parsed</small> | `string $url`, `array $data` |
| `ProviderRequestFailed`<br><small>When the HTTP request fails</small> | `string $url`, `Response $response` |

</div>

## Agent Executor Events

Fired by the executor during the agent tool loop.

<div class="full-width-table">

| Event | Properties |
|-------|-----------|
| `AgentStarted`<br><small>Agent execution begins</small> | `?string $agentKey`, `?int $maxSteps`, `bool $concurrent` |
| `AgentStepStarted`<br><small>Before each step in the tool loop</small> | `int $stepNumber`, `?string $agentKey` |
| `AgentStepCompleted`<br><small>After each step completes</small> | `int $stepNumber`, `FinishReason $finishReason`, `Usage $usage`, `?string $agentKey` |
| `AgentToolCallStarted`<br><small>Before a tool is invoked</small> | `ToolCall $toolCall`, `?string $agentKey`, `?int $stepNumber` |
| `AgentToolCallCompleted`<br><small>After a tool returns a result</small> | `ToolCall $toolCall`, `ToolResult $result`, `?string $agentKey`, `?int $stepNumber` |
| `AgentToolCallFailed`<br><small>When a tool throws an exception</small> | `ToolCall $toolCall`, `Throwable $exception`, `?string $agentKey`, `?int $stepNumber` |
| `AgentCompleted`<br><small>Agent execution finishes (always fires, even on error)</small> | `array $steps`, `Usage $usage`, `?string $agentKey` |
| `AgentMaxStepsExceeded`<br><small>Agent hit the max steps limit</small> | `int $limit`, `array $steps`, `?string $agentKey` |

</div>

## Stream Events

Fired during streaming responses. All stream events implement `ShouldBroadcastNow` for real-time WebSocket delivery.

<div class="full-width-table">

| Event | Properties |
|-------|-----------|
| `StreamStarted`<br><small>Stream iteration begins</small> | `?Channel $channel` |
| `StreamChunkReceived`<br><small>A text chunk arrives</small> | `Channel $channel`, `string $text` |
| `StreamToolCallReceived`<br><small>Tool calls arrive in the stream</small> | `Channel $channel`, `array $toolCalls` |
| `StreamThinkingReceived`<br><small>A thinking/reasoning chunk arrives</small> | `Channel $channel`, `string $text` |
| `StreamCompleted`<br><small>Stream finishes</small> | `Channel $channel`, `string $text`, `?array $usage`, `?FinishReason`, `?string $error` |

</div>

## Modality Events

Fired when a modality handler (text, object, image, audio, embedding) processes a request.

<div class="full-width-table">

| Event | Properties |
|-------|-----------|
| `ModalityStarted`<br><small>Before the modality handler runs</small> | `Modality $modality`, `string $provider`, `string $model` |
| `ModalityCompleted`<br><small>After the modality handler returns</small> | `Modality $modality`, `string $provider`, `string $model`, `?Usage $usage` |

</div>

## Execution Events

Fired during queued agent execution. All execution events implement `ShouldBroadcastNow` for real-time WebSocket delivery.

<div class="full-width-table">

| Event | Properties |
|-------|-----------|
| `ExecutionQueued`<br><small>Job is dispatched to the queue</small> | `?int $executionId`, `?Channel $channel` |
| `ExecutionProcessing`<br><small>Job starts processing in the worker</small> | `?int $executionId`, `?Channel $channel` |
| `ExecutionCompleted`<br><small>Job finishes successfully</small> | `?int $executionId`, `?Channel $channel` |
| `ExecutionFailed`<br><small>Job fails after all retries</small> | `?int $executionId`, `string $error`, `?Channel $channel` |

</div>

## Persistence Events

Fired when conversation messages are stored to the database.

<div class="full-width-table">

| Event | Properties |
|-------|-----------|
| `ConversationMessageStored`<br><small>After a message is persisted to a conversation</small> | `int $conversationId`, `int $messageId`, `Role $role`, `?string $agent` |

</div>

## Listening to Events

### EventServiceProvider

Register listeners in your `EventServiceProvider`:

```php
use Atlasphp\Atlas\Events\AgentCompleted;
use Atlasphp\Atlas\Events\AgentToolCallFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AgentCompleted::class => [
            LogAgentCompletion::class,
            TrackTokenUsage::class,
        ],
        AgentToolCallFailed::class => [
            AlertToolFailure::class,
        ],
    ];
}
```

### Inline Listeners

Use `Event::listen` for quick one-off listeners:

```php
use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(ProviderRequestCompleted::class, function (ProviderRequestCompleted $event) {
    Log::info('Provider call completed', ['url' => $event->url]);
});
```

### Event Subscriber

Handle multiple events in a single class:

```php
use Atlasphp\Atlas\Events\AgentStarted;
use Atlasphp\Atlas\Events\AgentCompleted;
use Atlasphp\Atlas\Events\AgentMaxStepsExceeded;
use Illuminate\Events\Dispatcher;

class AgentEventSubscriber
{
    public function handleStarted(AgentStarted $event): void
    {
        Log::debug('Agent starting', ['agent' => $event->agentKey]);
    }

    public function handleCompleted(AgentCompleted $event): void
    {
        Log::info('Agent completed', [
            'agent' => $event->agentKey,
            'steps' => count($event->steps),
            'tokens' => $event->usage->inputTokens + $event->usage->outputTokens,
        ]);
    }

    public function handleMaxSteps(AgentMaxStepsExceeded $event): void
    {
        Log::warning('Agent exceeded max steps', [
            'agent' => $event->agentKey,
            'limit' => $event->limit,
        ]);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AgentStarted::class, [self::class, 'handleStarted']);
        $events->listen(AgentCompleted::class, [self::class, 'handleCompleted']);
        $events->listen(AgentMaxStepsExceeded::class, [self::class, 'handleMaxSteps']);
    }
}
```

Register the subscriber in your `EventServiceProvider`:

```php
protected $subscribe = [
    AgentEventSubscriber::class,
];
```

## Practical Examples

### Cost Tracking

Track token usage across all agent executions:

```php
use Atlasphp\Atlas\Events\AgentStepCompleted;

class TrackCosts
{
    public function handle(AgentStepCompleted $event): void
    {
        DB::table('usage_logs')->insert([
            'agent' => $event->agentKey,
            'step' => $event->stepNumber,
            'input_tokens' => $event->usage->inputTokens,
            'output_tokens' => $event->usage->outputTokens,
            'created_at' => now(),
        ]);
    }
}
```

### Provider Request Metrics

Monitor latency and failures at the HTTP layer:

```php
use Atlasphp\Atlas\Events\ProviderRequestStarted;
use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;

class ProviderMetrics
{
    private static array $timers = [];

    public function handleStarted(ProviderRequestStarted $event): void
    {
        self::$timers[$event->url] = microtime(true);
    }

    public function handleCompleted(ProviderRequestCompleted $event): void
    {
        $duration = microtime(true) - (self::$timers[$event->url] ?? microtime(true));
        unset(self::$timers[$event->url]);

        Log::info('Provider request', [
            'url' => $event->url,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    public function handleFailed(ProviderRequestFailed $event): void
    {
        unset(self::$timers[$event->url]);

        Log::error('Provider request failed', ['url' => $event->url]);
    }
}
```

### Alerting on Tool Failures

```php
use Atlasphp\Atlas\Events\AgentToolCallFailed;
use Illuminate\Support\Facades\Notification;

class AlertToolFailure
{
    public function handle(AgentToolCallFailed $event): void
    {
        if ($this->isCriticalTool($event->toolCall->name)) {
            Notification::route('slack', config('services.slack.alerts'))
                ->notify(new ToolFailureAlert(
                    tool: $event->toolCall->name,
                    error: $event->exception->getMessage(),
                    agent: $event->agentKey,
                ));
        }
    }

    private function isCriticalTool(string $name): bool
    {
        return in_array($name, ['process_payment', 'send_email', 'update_order']);
    }
}
```

## Broadcasting Events

Stream events and execution events implement `ShouldBroadcastNow`, making them available for real-time WebSocket delivery through Laravel Broadcasting (Pusher, Ably, Reverb, etc.).

**Stream events** broadcast chunks, tool calls, and thinking content as they arrive — ideal for building real-time chat interfaces.

**Execution events** broadcast queue job lifecycle — useful for showing progress indicators when agents run in the background.

Both use the `Channel` object to target the correct broadcast channel. See [Streaming](/modalities/text) for setup details.

## Event Lifecycle

A typical agent execution with tool calls fires events in this order:

```
1. ModalityStarted           ← modality wraps the entire execution
2. AgentStarted
3. AgentStepStarted
4.   ProviderRequestStarted
5.   ProviderRequestCompleted
6. AgentStepCompleted
7. AgentToolCallStarted
8. AgentToolCallCompleted
9. AgentStepStarted          ← next step begins
10.  ProviderRequestStarted
11.  ProviderRequestCompleted
12. AgentStepCompleted       ← no more tool calls
13. AgentCompleted
14. ModalityCompleted         ← modality closes with usage
```

For queued executions, the lifecycle is wrapped:

```
1. ExecutionQueued
2. ExecutionProcessing
3. AgentStarted
   ... (agent lifecycle as above) ...
4. AgentCompleted
5. ConversationMessageStored  ← if persistence is enabled
6. ExecutionCompleted
```

If the agent fails, `ExecutionFailed` fires instead of `ExecutionCompleted`.

## API Reference

All 23 events at a glance:

<div class="full-width-table">

| # | Event | Group | Broadcasts |
|---|-------|-------|------------|
| 1 | `ProviderRequestStarted` | Provider | No |
| 2 | `ProviderRequestCompleted` | Provider | No |
| 3 | `ProviderRequestFailed` | Provider | No |
| 4 | `AgentStarted` | Executor | No |
| 5 | `AgentStepStarted` | Executor | No |
| 6 | `AgentStepCompleted` | Executor | No |
| 7 | `AgentToolCallStarted` | Executor | No |
| 8 | `AgentToolCallCompleted` | Executor | No |
| 9 | `AgentToolCallFailed` | Executor | No |
| 10 | `AgentCompleted` | Executor | No |
| 11 | `AgentMaxStepsExceeded` | Executor | No |
| 12 | `StreamStarted` | Stream | Yes |
| 13 | `StreamChunkReceived` | Stream | Yes |
| 14 | `StreamToolCallReceived` | Stream | Yes |
| 15 | `StreamThinkingReceived` | Stream | Yes |
| 16 | `StreamCompleted` | Stream | Yes |
| 17 | `ModalityStarted` | Modality | No |
| 18 | `ModalityCompleted` | Modality | No |
| 19 | `ExecutionQueued` | Execution | Yes |
| 20 | `ExecutionProcessing` | Execution | Yes |
| 21 | `ExecutionCompleted` | Execution | Yes |
| 22 | `ExecutionFailed` | Execution | Yes |
| 23 | `ConversationMessageStored` | Persistence | No |
| 24 | `VoiceCallStarted` | Voice | No |
| 25 | `VoiceCallCompleted` | Voice | No |
| 26 | `VoiceSessionClosed` | Voice | No |
| 27 | `VoiceToolCallRequested` | Voice | No |
| 28 | `VoiceAudioDelta` | Voice | Yes |
| 29 | `VoiceTranscriptDelta` | Voice | Yes |

</div>

## Voice Events

Events fired during voice call lifecycle.

### VoiceCallStarted

Fired when a voice call record is created and the session is ready for connection.

| Property | Type | Description |
|----------|------|-------------|
| `voiceCallId` | `int` | VoiceCall record ID |
| `conversationId` | `?int` | Linked conversation (nullable) |
| `sessionId` | `string` | Provider session ID |
| `provider` | `string` | Provider name |
| `agent` | `?string` | Agent key |

### VoiceCallCompleted

Fired when a voice call completes with the full transcript. This is the primary event for consumers to post-process voice calls — generate summaries, create conversation messages, embed into memory.

| Property | Type | Description |
|----------|------|-------------|
| `voiceCallId` | `int` | VoiceCall record ID |
| `conversationId` | `?int` | Linked conversation |
| `sessionId` | `string` | Provider session ID |
| `transcript` | `array` | Complete transcript `[{role, content}]` |
| `durationMs` | `?int` | Wall-clock duration |

Atlas fires this event and stores the transcript. Post-processing is entirely consumer-owned — you decide whether to summarize, create messages, embed into memory, or do nothing.

```php
use Atlasphp\Atlas\Events\VoiceCallCompleted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Atlasphp\Atlas\Persistence\Services\ConversationService;

Event::listen(VoiceCallCompleted::class, function ($event) {
    if ($event->transcript === []) return;

    $voiceCall = VoiceCall::find($event->voiceCallId);

    $formatted = collect($event->transcript)
        ->map(fn ($t) => ucfirst($t['role']).': '.$t['content'])
        ->implode("\n");

    try {
        // Summarize with a cheap, fast model
        $response = Atlas::text('xai', 'grok-3-mini-fast')
            ->instructions('Summarize this voice call in 2-3 sentences.')
            ->message($formatted)
            ->asText();

        // Store summary on the call record
        $voiceCall->update(['summary' => $response->text]);

        // Optionally inject into conversation so the text agent sees it
        if ($event->conversationId) {
            $conversations = app(ConversationService::class);
            $conversation = $conversations->find($event->conversationId);
            $conversations->addMessage($conversation, new SystemMessage(
                content: "[Voice call summary]\n{$response->text}"
            ));
        }
    } catch (\Throwable $e) {
        logger()->error('Voice summarization failed', ['id' => $event->voiceCallId]);
    }
});
```

See [Voice — Post-Processing Patterns](/modalities/voice#post-processing-patterns) for more options.

## Next Steps

- [Middleware](/features/middleware) — Modify behavior with middleware
- [Streaming](/modalities/text) — Real-time streaming with broadcast events
- [Testing](/advanced/testing) — Test agent execution
