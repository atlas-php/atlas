<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

/**
 * Immutable response from agent execution.
 *
 * Captures text output, structured data, tool calls, usage statistics,
 * and additional metadata from the AI provider response.
 */
final readonly class AgentResponse
{
    /**
     * @param  string|null  $text  The text response from the agent.
     * @param  mixed  $structured  Structured output when using schema.
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls  Tool calls made by the agent.
     * @param  array<string, int>  $usage  Token usage statistics.
     * @param  array<string, mixed>  $metadata  Additional response metadata.
     */
    public function __construct(
        public ?string $text = null,
        public mixed $structured = null,
        public array $toolCalls = [],
        public array $usage = [],
        public array $metadata = [],
    ) {}

    /**
     * Create a text response.
     *
     * @param  string  $text  The response text.
     */
    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * Create a structured response.
     *
     * @param  mixed  $data  The structured data.
     */
    public static function structured(mixed $data): self
    {
        return new self(structured: $data);
    }

    /**
     * Create a response with tool calls.
     *
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls
     */
    public static function withToolCalls(array $toolCalls): self
    {
        return new self(toolCalls: $toolCalls);
    }

    /**
     * Create an empty response.
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Check if the response has text.
     */
    public function hasText(): bool
    {
        return $this->text !== null && $this->text !== '';
    }

    /**
     * Check if the response has structured data.
     */
    public function hasStructured(): bool
    {
        return $this->structured !== null;
    }

    /**
     * Check if the response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Check if the response has usage data.
     */
    public function hasUsage(): bool
    {
        return $this->usage !== [];
    }

    /**
     * Get total tokens used.
     */
    public function totalTokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? 0);
    }

    /**
     * Get prompt tokens used.
     */
    public function promptTokens(): int
    {
        return (int) ($this->usage['prompt_tokens'] ?? 0);
    }

    /**
     * Get completion tokens used.
     */
    public function completionTokens(): int
    {
        return (int) ($this->usage['completion_tokens'] ?? 0);
    }

    /**
     * Get a metadata value.
     *
     * @param  string  $key  The metadata key.
     * @param  mixed  $default  Default value if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Create a new response with merged metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->text,
            $this->structured,
            $this->toolCalls,
            $this->usage,
            array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Create a new response with usage data.
     *
     * @param  array<string, int>  $usage
     */
    public function withUsage(array $usage): self
    {
        return new self(
            $this->text,
            $this->structured,
            $this->toolCalls,
            $usage,
            $this->metadata,
        );
    }
}
