<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Prism\Prism\Contracts\Schema;

/**
 * Contract for agent execution implementations.
 *
 * Defines the interface for executing agents with conversation context
 * and optional structured output schemas. Supports both blocking and
 * streaming execution modes.
 */
interface AgentExecutorContract
{
    /**
     * Execute an agent with the given input.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext|null  $context  Optional execution context with messages and variables.
     * @param  Schema|null  $schema  Optional schema for structured output.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
        ?array $retry = null,
    ): AgentResponse;

    /**
     * Stream a response from an agent.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext|null  $context  Optional execution context with messages and variables.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function stream(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?array $retry = null,
    ): StreamResponse;
}
