# Events

Observe the full lifecycle of providers, agents, tools, streams, and queued executions with Laravel events.

::: tip Events vs Pipelines
**Pipelines** modify behavior — they wrap execution and can change inputs/outputs. **Events** observe behavior — they fire at specific lifecycle points and are purely informational. Use pipelines when you need to intercept or transform; use events when you need to log, broadcast, or react.
:::

## Overview

Atlas fires events across six groups. Events fire automatically — no configuration needed to enable them. Every event is a standard Laravel event, so you can listen with `Event::listen`, an `EventServiceProvider`, or Laravel's event discovery.

All events live in the `Atlasphp\Atlas\Events` namespace.

## Provider Events

Fired by the shared `HttpClient` on every API call to a provider.

<div class="full-width-table">

| Event | Properties | When |
|-------|-----------|------|
| `ProviderRequestStarted` | `string $url`, `array $body` | Before the HTTP request is sent |
| `ProviderRequestCompleted` | `string $url`, `array $data` | After a successful response is parsed |
| `ProviderRequestFailed` | `string $url`, `mixed $response` | When the HTTP request fails |

</div>

## Agent Executor Events

Fired by the executor during the agent tool loop.

<div class="full-width-table">

| Event | Properties | When |
|-------|-----------|------|
| `AgentStarted` | `?string $agentKey`, `?int $maxSteps`, `bool $concurrent` | Agent execution begins |
| `AgentStepStarted` | `int $stepNumber`, `?string $agentKey` | Before each step in the tool loop |
| `AgentStepCompleted` | `int $stepNumber`, `FinishReason $finishReason`, `Usage $usage`, `?string $agentKey` | After each step completes |
| `AgentToolCallStarted` | `ToolCall $toolCall`, `?string $agentKey`, `?int $stepNumber` | Before a tool is invoked |
| `AgentToolCallCompleted` | `ToolCall $toolCall`, `ToolResult $result`, `?string $agentKey`, `?int $stepNumber` | After a tool returns a result |
| `AgentToolCallFailed` | `ToolCall $toolCall`, `Throwable $exception`, `?string $agentKey`, `?int $stepNumber` | When a tool throws an exception |
| `AgentCompleted` | `array $steps`, `Usage $usage`, `?string $agentKey` | Agent execution finishes successfully |
| `AgentMaxStepsExceeded` | `int $limit`, `array $steps`, `?string $agentKey` | Agent hit the max steps limit |

</div>

## Stream Events

Fired during streaming responses. All stream events implement `ShouldBroadcastNow` for real-time WebSocket delivery.

<div class="full-width-table">

| Event | Properties | When |
|-------|-----------|------|
| `StreamStarted` | `?Channel $channel` | Stream connection opens |
| `StreamChunkReceived` | `Channel $channel`, `string $text` | A text chunk arrives |
| `StreamToolCallReceived` | `Channel $channel`, `array $toolCalls` | Tool calls arrive in the stream |
| `StreamThinkingReceived` | `Channel $channel`, `string $text` | A thinking/reasoning chunk arrives |
| `StreamCompleted` | `Channel $channel`, `string $text`, `?array $usage`, `?FinishReason`, `?string $error` | Stream finishes |

</div>

## Modality Events

Fired when a modality handler (text, object, image, audio, embedding) processes a request.

<div class="full-width-table">

| Event | Properties | When |
|-------|-----------|------|
| `ModalityStarted` | `Modality $modality`, `string $provider`, `string $model` | Before the modality handler runs |
| `ModalityCompleted` | `Modality $modality`, `string $provider`, `string $model`, `?Usage $usage` | After the modality handler returns |

</div>

## Execution Events

Fired during queued agent execution. All execution events implement `ShouldBroadcastNow` for real-time WebSocket delivery.

<div class="full-width-table">

| Event | Properties | When |
|-------|-----------|------|
| `ExecutionQueued` | `?int $executionId`, `?Channel $channel` | Job is dispatched to the queue |
| `ExecutionProcessing` | `?int $executionId`, `?Channel $channel` | Job starts processing |
| `ExecutionCompleted` | `?int $executionId`, `?Channel $channel` | Job finishes successfully |
| `ExecutionFailed` | `?int $executionId`, `string $error`, `?Channel $channel` | Job fails |

</div>

## Persistence Events

Fired when conversation messages are stored to the database.

<div class="full-width-table">

| Event | Properties | When |
|-------|-----------|------|
| `ConversationMessageStored` | `int $conversationId`, `int $messageId`, `Role $role`, `?string $agent` | After a message is persisted |

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
            'tokens' => $event->usage->promptTokens + $event->usage->completionTokens,
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
            'prompt_tokens' => $event->usage->promptTokens,
            'completion_tokens' => $event->usage->completionTokens,
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
1. AgentStarted
2. ModalityStarted
3. ProviderRequestStarted
4. ProviderRequestCompleted
5. ModalityCompleted
6. AgentStepStarted
7. AgentToolCallStarted
8. AgentToolCallCompleted
9. AgentStepCompleted
10. ModalityStarted          ← next step begins
11. ProviderRequestStarted
12. ProviderRequestCompleted
13. ModalityCompleted
14. AgentStepStarted
15. AgentStepCompleted       ← no more tool calls
16. AgentCompleted
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

</div>

## Next Steps

- [Pipelines](/features/middleware) — Modify behavior with pipeline hooks
- [Streaming](/modalities/text) — Real-time streaming with broadcast events
- [Testing](/advanced/testing) — Test agent execution
