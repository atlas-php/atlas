<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Prism\Prism\Contracts\Schema;

/**
 * Immutable builder for conversation context in multi-turn chats.
 *
 * Provides a fluent API for building conversation context with messages,
 * variables for system prompt interpolation, and metadata for pipeline middleware.
 * Each method returns a new instance to maintain immutability.
 */
final readonly class MessageContextBuilder
{
    /**
     * @param  AtlasManager  $manager  The Atlas manager for execution.
     * @param  array<int, array{role: string, content: string}>  $messages  Conversation history.
     * @param  array<string, mixed>  $variables  Variables for system prompt interpolation.
     * @param  array<string, mixed>  $metadata  Additional metadata for pipeline middleware.
     */
    public function __construct(
        private AtlasManager $manager,
        private array $messages = [],
        private array $variables = [],
        private array $metadata = [],
    ) {}

    /**
     * Create a new builder with the given variables.
     *
     * Variables are used for system prompt interpolation (e.g., {user_name}).
     *
     * @param  array<string, mixed>  $variables  Variables to set.
     */
    public function withVariables(array $variables): self
    {
        return new self(
            $this->manager,
            $this->messages,
            $variables,
            $this->metadata,
        );
    }

    /**
     * Create a new builder with the given metadata.
     *
     * Metadata is passed to pipeline middleware for custom processing.
     *
     * @param  array<string, mixed>  $metadata  Metadata to set.
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->manager,
            $this->messages,
            $this->variables,
            $metadata,
        );
    }

    /**
     * Execute a chat with an agent using this context.
     *
     * When stream is false (default), returns an AgentResponse with the complete response.
     * When stream is true, returns a StreamResponse that can be iterated for real-time events.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     * @param  string  $input  The user input message.
     * @param  Schema|null  $schema  Optional schema for structured output (not supported with streaming).
     * @param  bool  $stream  Whether to stream the response.
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?Schema $schema = null,
        bool $stream = false,
    ): AgentResponse|StreamResponse {
        $context = new ExecutionContext(
            messages: $this->messages,
            variables: $this->variables,
            metadata: $this->metadata,
        );

        if ($stream) {
            return $this->manager->streamWithContext($agent, $input, $context);
        }

        return $this->manager->executeWithContext($agent, $input, $context, $schema);
    }

    /**
     * Get the conversation messages.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the variables.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
