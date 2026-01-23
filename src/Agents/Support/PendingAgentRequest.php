<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Providers\Support\HasRetrySupport;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Prism\Prism\Contracts\Schema;

/**
 * Fluent builder for agent chat operations.
 *
 * Provides a fluent API for configuring and executing chat operations
 * with messages, variables, metadata, and retry support. Uses immutable
 * cloning for method chaining.
 */
final class PendingAgentRequest
{
    use HasRetrySupport;

    /**
     * Conversation history.
     *
     * @var array<int, array{role: string, content: string}>
     */
    private array $messages = [];

    /**
     * Variables for system prompt interpolation.
     *
     * @var array<string, mixed>
     */
    private array $variables = [];

    /**
     * Additional metadata for pipeline middleware.
     *
     * @var array<string, mixed>
     */
    private array $metadata = [];

    public function __construct(
        private readonly AgentResolver $agentResolver,
        private readonly AgentExecutorContract $agentExecutor,
        private readonly string|AgentContract $agent,
    ) {}

    /**
     * Set conversation history messages.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function withMessages(array $messages): self
    {
        $clone = clone $this;
        $clone->messages = $messages;

        return $clone;
    }

    /**
     * Set variables for system prompt interpolation.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): self
    {
        $clone = clone $this;
        $clone->variables = $variables;

        return $clone;
    }

    /**
     * Set metadata for pipeline middleware and tools.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;

        return $clone;
    }

    /**
     * Execute a chat with the configured agent.
     *
     * When stream is false (default), returns an AgentResponse with the complete response.
     * When stream is true, returns a StreamResponse that can be iterated for real-time events.
     *
     * @param  string  $input  The user input message.
     * @param  Schema|null  $schema  Optional schema for structured output (not supported with streaming).
     * @param  bool  $stream  Whether to stream the response.
     *
     * @throws \InvalidArgumentException If both schema and stream are provided.
     */
    public function chat(
        string $input,
        ?Schema $schema = null,
        bool $stream = false,
    ): AgentResponse|StreamResponse {
        $resolvedAgent = $this->agentResolver->resolve($this->agent);

        $context = ($this->messages !== [] || $this->variables !== [] || $this->metadata !== [])
            ? new ExecutionContext(
                messages: $this->messages,
                variables: $this->variables,
                metadata: $this->metadata,
            )
            : null;

        if ($stream) {
            if ($schema !== null) {
                throw new \InvalidArgumentException(
                    'Streaming does not support structured output (schema). Use stream: false for structured responses.'
                );
            }

            return $this->agentExecutor->stream(
                $resolvedAgent,
                $input,
                $context,
                $this->getRetryArray(),
            );
        }

        return $this->agentExecutor->execute(
            $resolvedAgent,
            $input,
            $context,
            $schema,
            $this->getRetryArray(),
        );
    }
}
