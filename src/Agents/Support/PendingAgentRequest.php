<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Providers\Support\HasMediaSupport;
use Atlasphp\Atlas\Providers\Support\HasMessagesSupport;
use Atlasphp\Atlas\Providers\Support\HasMetadataSupport;
use Atlasphp\Atlas\Providers\Support\HasProviderCallbacks;
use Atlasphp\Atlas\Providers\Support\HasProviderSupport;
use Atlasphp\Atlas\Providers\Support\HasRetrySupport;
use Atlasphp\Atlas\Providers\Support\HasSchemaSupport;
use Atlasphp\Atlas\Providers\Support\HasStructuredModeSupport;
use Atlasphp\Atlas\Providers\Support\HasToolChoiceSupport;
use Atlasphp\Atlas\Providers\Support\HasVariablesSupport;
use Atlasphp\Atlas\Streaming\StreamResponse;

/**
 * Fluent builder for agent chat operations.
 *
 * Provides a fluent API for configuring and executing chat operations
 * with messages, variables, metadata, schema, provider/model overrides,
 * and retry support. Uses immutable cloning for method chaining.
 */
final class PendingAgentRequest
{
    use HasMediaSupport;
    use HasMessagesSupport;
    use HasMetadataSupport;
    use HasProviderCallbacks;
    use HasProviderSupport;
    use HasRetrySupport;
    use HasSchemaSupport;
    use HasStructuredModeSupport;
    use HasToolChoiceSupport;
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

        // Resolve provider and apply any provider-specific callbacks
        $resolvedProvider = $this->getProviderOverride()
            ?? $resolvedAgent->provider()
            ?? config('atlas.chat.provider');

        $self = $this->applyProviderCallbacks($resolvedProvider);

        $messages = $self->getMessages();
        $variables = $self->getVariables();
        $metadata = $self->getMetadata();
        $schema = $self->getSchema();
        $providerOverride = $self->getProviderOverride();
        $modelOverride = $self->getModelOverride();
        $currentAttachments = $self->getCurrentAttachments();
        $toolChoice = $self->getToolChoice();
        $providerOptions = $self->getProviderOptions();

        // Build context if any configuration is present
        $hasConfig = $messages !== []
            || $variables !== []
            || $metadata !== []
            || $providerOverride !== null
            || $modelOverride !== null
            || $currentAttachments !== []
            || $toolChoice !== null
            || $providerOptions !== [];

        $context = $hasConfig
            ? new ExecutionContext(
                messages: $messages,
                variables: $variables,
                metadata: $metadata,
                providerOverride: $providerOverride,
                modelOverride: $modelOverride,
                currentAttachments: $currentAttachments,
                toolChoice: $toolChoice,
                providerOptions: $providerOptions,
            )
            : null;

        if ($stream) {
            if ($schema !== null) {
                throw new \InvalidArgumentException(
                    'Streaming does not support structured output (schema). Use stream: false for structured responses.'
                );
            }

            return $self->agentExecutor->stream(
                $resolvedAgent,
                $input,
                $context,
                $self->getRetryArray(),
            );
        }

        return $self->agentExecutor->execute(
            $resolvedAgent,
            $input,
            $context,
            $schema,
            $self->getRetryArray(),
            $self->getStructuredMode(),
        );
    }
}
