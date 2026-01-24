<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;

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
     * @param  ExecutionContext  $context  The execution context.
     * @param  AgentResponse  $response  The response that was returned.
     * @param  int  $timestamp  Unix timestamp when the request was made.
     */
    public function __construct(
        public AgentContract $agent,
        public string $input,
        public ExecutionContext $context,
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
        if (! $this->context->hasMeta($key)) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        return $this->context->getMeta($key) === $value;
    }

    /**
     * Check if a specific Prism method was called.
     *
     * @param  string  $method  The method name to check for.
     */
    public function hasPrismCall(string $method): bool
    {
        foreach ($this->context->prismCalls as $call) {
            if ($call['method'] === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get arguments for a specific Prism method call.
     *
     * @param  string  $method  The method name.
     * @return array<int, mixed>|null The arguments or null if not found.
     */
    public function getPrismCallArgs(string $method): ?array
    {
        foreach ($this->context->prismCalls as $call) {
            if ($call['method'] === $method) {
                return $call['args'];
            }
        }

        return null;
    }
}
