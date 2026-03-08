# Events

Observe agent and tool lifecycle with Laravel events.

::: tip Events vs Pipelines
**Pipelines** modify behavior — they wrap execution and can change inputs/outputs. **Events** observe behavior — they fire after pipelines and are purely informational. Use pipelines when you need to intercept or transform; use events when you need to log, broadcast, or react.
:::

## Configuration

Events are enabled by default. Disable them to eliminate dispatch overhead when you have no listeners:

```env
ATLAS_EVENTS_ENABLED=false
```

Or in `config/atlas.php`:

```php
'events' => [
    'enabled' => env('ATLAS_EVENTS_ENABLED', true),
],
```

See [Configuration](/getting-started/configuration) for the full config reference.

## Agent Events

<div class="full-width-table">

| Event | When | Properties |
|-------|------|------------|
| `AgentExecuting` | Before Prism API call | `$agent`, `$input`, `$context` |
| `AgentExecuted` | After successful execution | `$agent`, `$input`, `$context`, `$response` |
| `AgentStreaming` | When streaming begins | `$agent`, `$input`, `$context` |
| `AgentStreamed` | When streaming completes | `$agent`, `$input`, `$context`, `$events` |
| `AgentFailed` | On unrecoverable failure | `$agent`, `$input`, `$context`, `$exception` |

</div>

All agent events live in `Atlasphp\Atlas\Agents\Events`. The `AgentStreamChunk` broadcast event (also in this namespace) is used for WebSocket delivery — see [Broadcasting to WebSockets](/capabilities/streaming#broadcasting-to-websockets).

::: info AgentFailed
`AgentFailed` only fires when the error recovery pipeline returns `null` (unrecoverable). If the pipeline recovers the error, the event is not dispatched.
:::

## Tool Events

<div class="full-width-table">

| Event | When | Properties |
|-------|------|------------|
| `ToolExecuting` | Before tool `handle()` | `$tool`, `$params`, `$context` |
| `ToolExecuted` | After tool `handle()` | `$tool`, `$params`, `$context`, `$result`, `$duration` |

</div>

All tool events live in `Atlasphp\Atlas\Tools\Events`. The `$duration` property on `ToolExecuted` is a `float` representing milliseconds.

## Listening to Events

Register listeners in your `EventServiceProvider` or use Laravel's event discovery:

```php
use Atlasphp\Atlas\Agents\Events\AgentExecuted;
use Atlasphp\Atlas\Tools\Events\ToolExecuted;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AgentExecuted::class => [
            LogAgentExecution::class,
        ],
        ToolExecuted::class => [
            TrackToolUsage::class,
        ],
    ];
}
```

### Example: Logging Agent Execution

```php
use Atlasphp\Atlas\Agents\Events\AgentExecuted;

class LogAgentExecution
{
    public function handle(AgentExecuted $event): void
    {
        Log::info('Agent executed', [
            'agent' => $event->agent->key(),
            'input' => $event->input,
            'tokens' => $event->response->usage()->promptTokens
                      + $event->response->usage()->completionTokens,
        ]);
    }
}
```

### Example: Event Subscriber

Handle multiple events in a single class:

```php
use Atlasphp\Atlas\Agents\Events\AgentExecuting;
use Atlasphp\Atlas\Agents\Events\AgentExecuted;
use Atlasphp\Atlas\Agents\Events\AgentFailed;
use Illuminate\Events\Dispatcher;

class AgentEventSubscriber
{
    public function handleExecuting(AgentExecuting $event): void
    {
        Log::debug('Agent starting', ['agent' => $event->agent->key()]);
    }

    public function handleExecuted(AgentExecuted $event): void
    {
        Log::info('Agent completed', ['agent' => $event->agent->key()]);
    }

    public function handleFailed(AgentFailed $event): void
    {
        Log::error('Agent failed', [
            'agent' => $event->agent->key(),
            'error' => $event->exception->getMessage(),
        ]);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AgentExecuting::class, [self::class, 'handleExecuting']);
        $events->listen(AgentExecuted::class, [self::class, 'handleExecuted']);
        $events->listen(AgentFailed::class, [self::class, 'handleFailed']);
    }
}
```

Register the subscriber in your `EventServiceProvider`:

```php
protected $subscribe = [
    AgentEventSubscriber::class,
];
```

## Next Steps

- [Pipelines](/core-concepts/pipelines) — Modify behavior with middleware hooks
- [Streaming](/capabilities/streaming) — Real-time streaming with events
- [Testing](/advanced/testing) — Test agent execution
