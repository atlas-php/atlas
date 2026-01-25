<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Prism\Prism\Facades\Prism;

/**
 * Main manager for Atlas capabilities.
 *
 * Provides the primary API for agents and Prism operations. Acts as a thin
 * wrapper around Prism with pipeline support for observability.
 *
 * @mixin \Prism\Prism\Facades\Prism
 */
class AtlasManager
{
    public function __construct(
        protected AgentResolver $agentResolver,
        protected AgentExecutorContract $agentExecutor,
        protected PipelineRunner $pipelineRunner,
    ) {}

    /**
     * Start building a chat request for the given agent.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     */
    public function agent(string|AgentContract $agent): PendingAgentRequest
    {
        return new PendingAgentRequest(
            $this->agentResolver,
            $this->agentExecutor,
            $agent,
        );
    }

    /**
     * Forward Prism methods for full API compatibility.
     *
     * Wraps Prism requests in PrismProxy for pipeline observability.
     * Known modules (text, structured, etc.) have pipeline hooks;
     * unknown modules pass through without hooks.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): PrismProxy
    {
        /** @var object $prismRequest */
        $prismRequest = Prism::{$method}(...$arguments);

        return new PrismProxy($this->pipelineRunner, $prismRequest, $method);
    }
}
