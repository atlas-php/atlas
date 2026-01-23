<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Providers\Support\HasMessagesSupport;
use Atlasphp\Atlas\Providers\Support\HasMetadataSupport;
use Atlasphp\Atlas\Providers\Support\HasRetrySupport;
use Atlasphp\Atlas\Providers\Support\HasSchemaSupport;
use Atlasphp\Atlas\Providers\Support\HasVariablesSupport;
use Atlasphp\Atlas\Streaming\StreamResponse;

/**
 * Fluent builder for agent chat operations.
 *
 * Provides a fluent API for configuring and executing chat operations
 * with messages, variables, metadata, schema, and retry support. Uses
 * immutable cloning for method chaining.
 */
final class PendingAgentRequest
{
    use HasMessagesSupport;
    use HasMetadataSupport;
    use HasRetrySupport;
    use HasSchemaSupport;
    use HasVariablesSupport;

    public function __construct(
        private readonly AgentResolver $agentResolver,
        private readonly AgentExecutorContract $agentExecutor,
        private readonly string|AgentContract $agent,
    ) {}

    /**
     * Execute a chat with the configured agent.
     *
     * When stream is false (default), returns an AgentResponse with the complete response.
     * When stream is true, returns a StreamResponse that can be iterated for real-time events.
     *
     * @param  string  $input  The user input message.
     * @param  bool  $stream  Whether to stream the response.
     *
     * @throws \InvalidArgumentException If both schema and stream are provided.
     */
    public function chat(
        string $input,
        bool $stream = false,
    ): AgentResponse|StreamResponse {
        $resolvedAgent = $this->agentResolver->resolve($this->agent);

        $messages = $this->getMessages();
        $variables = $this->getVariables();
        $metadata = $this->getMetadata();
        $schema = $this->getSchema();

        $context = ($messages !== [] || $variables !== [] || $metadata !== [])
            ? new ExecutionContext(
                messages: $messages,
                variables: $variables,
                metadata: $metadata,
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
