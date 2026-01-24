<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Generator;
use Prism\Prism\Streaming\Events\StreamEvent;

/**
 * Contract for agent execution implementations.
 *
 * Defines the interface for executing agents with conversation context.
 * Supports both blocking and streaming execution modes.
 *
 * All Prism-specific configuration (schema, retry, structuredMode, toolChoice, etc.)
 * is captured in the ExecutionContext's prismCalls and replayed on the request.
 */
interface AgentExecutorContract
{
    /**
     * Execute an agent with the given input.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext  $context  Execution context with messages, variables, and Prism calls.
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
    ): AgentResponse;

    /**
     * Stream a response from an agent.
     *
     * Returns a Generator yielding Prism StreamEvents directly.
     * Consumers work with Prism's streaming API directly.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext  $context  Execution context with messages, variables, and Prism calls.
     * @return Generator<int, StreamEvent>
     */
    public function stream(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
    ): Generator;
}
