<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Jobs;

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;

/**
 * Queueable job that streams an agent response and broadcasts each chunk.
 *
 * Resolves the agent, executes via streaming, and broadcasts each stream event
 * as an AgentStreamChunk to the configured WebSocket channel.
 */
class BroadcastAgent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $agentKey  The agent registry key.
     * @param  string  $input  The user input message.
     * @param  array<string, mixed>  $serializedContext  Serialized AgentContext data from toArray().
     * @param  string  $requestId  Unique request identifier for channel scoping.
     */
    public function __construct(
        public readonly string $agentKey,
        public readonly string $input,
        public readonly array $serializedContext,
        public readonly string $requestId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AgentResolver $resolver, AgentExecutorContract $executor): void
    {
        $agent = $resolver->resolve($this->agentKey);
        $context = AgentContext::fromArray($this->serializedContext);

        $streamResponse = $executor->stream($agent, $this->input, $context);

        foreach ($streamResponse as $event) {
            $chunk = match (true) {
                $event instanceof StreamStartEvent => new AgentStreamChunk(
                    agentKey: $this->agentKey,
                    requestId: $this->requestId,
                    type: 'stream-start',
                    metadata: ['model' => $event->model, 'provider' => $event->provider],
                ),
                $event instanceof TextDeltaEvent => new AgentStreamChunk(
                    agentKey: $this->agentKey,
                    requestId: $this->requestId,
                    type: 'text-delta',
                    delta: $event->delta,
                ),
                $event instanceof StreamEndEvent => new AgentStreamChunk(
                    agentKey: $this->agentKey,
                    requestId: $this->requestId,
                    type: 'stream-end',
                    metadata: [
                        'finish_reason' => $event->finishReason->value,
                        'usage' => [
                            'prompt_tokens' => $event->usage->promptTokens,
                            'completion_tokens' => $event->usage->completionTokens,
                        ],
                    ],
                ),
                default => null,
            };

            if ($chunk !== null) {
                $this->dispatchEvent($chunk);
            }
        }
    }

    /**
     * Dispatch an event if events are enabled.
     */
    protected function dispatchEvent(object $event): void
    {
        if (config('atlas.events.enabled', true) === false) {
            return;
        }

        event($event);
    }
}
