<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Prism\Prism\Contracts\Schema;

/**
 * Value object representing a recorded agent request.
 *
 * Captures all details of an agent execution for later assertion
 * during testing.
 */
final readonly class RecordedRequest
{
    /**
     * @param  AgentContract  $agent  The agent that was executed.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext|null  $context  The execution context.
     * @param  Schema|null  $schema  The structured output schema if used.
     * @param  AgentResponse  $response  The response that was returned.
     * @param  int  $timestamp  Unix timestamp when the request was made.
     */
    public function __construct(
        public AgentContract $agent,
        public string $input,
        public ?ExecutionContext $context,
        public ?Schema $schema,
        public AgentResponse $response,
        public int $timestamp,
    ) {}

    /**
     * Get the agent key.
     */
    public function agentKey(): string
    {
        return $this->agent->key();
    }

    /**
     * Check if the input contains the given string.
     */
    public function inputContains(string $needle): bool
    {
        return str_contains($this->input, $needle);
    }

    /**
     * Check if the context has specific metadata.
     *
     * @param  string  $key  The metadata key.
     * @param  mixed  $value  Optional value to match.
     */
    public function hasMetadata(string $key, mixed $value = null): bool
    {
        if ($this->context === null) {
            return false;
        }

        if (! $this->context->hasMeta($key)) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        return $this->context->getMeta($key) === $value;
    }

    /**
     * Check if a schema was used.
     */
    public function hasSchema(): bool
    {
        return $this->schema !== null;
    }
}
