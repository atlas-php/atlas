<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Prism\Prism\Contracts\Schema;

/**
 * Contract for agent execution implementations.
 *
 * Defines the interface for executing agents with conversation context
 * and optional structured output schemas.
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
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
    ): AgentResponse;
}
