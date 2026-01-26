<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Generator;
use IteratorAggregate;
use Prism\Prism\Streaming\Events\StreamEvent;
use Traversable;

/**
 * Wrapper for agent streaming responses.
 *
 * Provides agent context alongside stream events. Implements
 * IteratorAggregate for seamless foreach iteration.
 *
 * @implements IteratorAggregate<int, StreamEvent>
 */
final class AgentStreamResponse implements IteratorAggregate
{
    /**
     * @var array<int, StreamEvent>
     */
    private array $collectedEvents = [];

    private bool $consumed = false;

    /**
     * @param  Generator<int, StreamEvent>  $stream
     */
    public function __construct(
        private Generator $stream,
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly ?string $systemPrompt,
        public readonly AgentContext $context,
    ) {}

    /**
     * Get the iterator for foreach iteration.
     *
     * Yields stream events while collecting them for post-iteration access.
     *
     * @return Traversable<int, StreamEvent>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->stream as $event) {
            $this->collectedEvents[] = $event;
            yield $event;
        }
        $this->consumed = true;
    }

    /**
     * Get the agent key.
     */
    public function agentKey(): string
    {
        return $this->agent->key();
    }

    /**
     * Get the agent name.
     */
    public function agentName(): string
    {
        return $this->agent->name();
    }

    /**
     * Get the agent description.
     */
    public function agentDescription(): ?string
    {
        return $this->agent->description();
    }

    /**
     * Get the pipeline metadata from context.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->context->metadata;
    }

    /**
     * Get the variables used for prompt interpolation.
     *
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        return $this->context->variables;
    }

    /**
     * Get all collected events after stream consumption.
     *
     * @return array<int, StreamEvent>
     */
    public function events(): array
    {
        return $this->collectedEvents;
    }

    /**
     * Check if the stream has been fully consumed.
     */
    public function isConsumed(): bool
    {
        return $this->consumed;
    }
}
