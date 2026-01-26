<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

/**
 * Wrapper for agent execution responses.
 *
 * Combines Prism's response with agent context, providing access to
 * agent-specific metadata alongside the AI response. Supports backward
 * compatible property access via __get magic method.
 */
final readonly class AgentResponse
{
    public function __construct(
        public PrismResponse|StructuredResponse $response,
        public AgentContract $agent,
        public string $input,
        public ?string $systemPrompt,
        public AgentContext $context,
    ) {}

    /**
     * Magic property access for backward compatibility.
     *
     * Allows accessing Prism response properties directly on the AgentResponse.
     * Example: $response->text instead of $response->response->text
     */
    public function __get(string $name): mixed
    {
        return $this->response->{$name};
    }

    /**
     * Get the text response.
     */
    public function text(): string
    {
        return $this->response->text;
    }

    /**
     * Get the usage statistics.
     */
    public function usage(): Usage
    {
        return $this->response->usage;
    }

    /**
     * Get the tool calls from the response.
     *
     * @return array<int, \Prism\Prism\ValueObjects\ToolCall>
     */
    public function toolCalls(): array
    {
        if ($this->response instanceof StructuredResponse) {
            return [];
        }

        return $this->response->toolCalls;
    }

    /**
     * Get the tool results from the response.
     *
     * @return array<int, \Prism\Prism\ValueObjects\ToolResult>
     */
    public function toolResults(): array
    {
        if ($this->response instanceof StructuredResponse) {
            return [];
        }

        return $this->response->toolResults;
    }

    /**
     * Get the response steps (multi-step agentic loop history).
     *
     * @return Collection<int, mixed>
     */
    public function steps(): Collection
    {
        return $this->response->steps;
    }

    /**
     * Get the finish reason.
     */
    public function finishReason(): FinishReason
    {
        return $this->response->finishReason;
    }

    /**
     * Get the response metadata.
     */
    public function meta(): Meta
    {
        return $this->response->meta;
    }

    /**
     * Get the messages from the response.
     *
     * @return Collection<int, \Prism\Prism\Contracts\Message>
     */
    public function messages(): Collection
    {
        return $this->response->messages;
    }

    /**
     * Check if this is a structured response.
     */
    public function isStructured(): bool
    {
        return $this->response instanceof StructuredResponse;
    }

    /**
     * Get the structured output if available.
     *
     * @return array<string, mixed>|null Returns null if not a structured response.
     */
    public function structured(): ?array
    {
        if ($this->response instanceof StructuredResponse) {
            return $this->response->structured;
        }

        return null;
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
}
