<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Agents\AnonymousAgent;
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
 * @mixin Prism
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
     * Create an anonymous agent with inline configuration.
     *
     * @param  string  $systemPrompt  The system prompt for the agent.
     * @param  string|null  $provider  AI provider name.
     * @param  string|null  $model  Model identifier.
     * @param  array<int, class-string>  $tools  Tool class names.
     * @param  string|null  $key  Optional unique key for the agent.
     */
    public function make(
        string $systemPrompt,
        ?string $provider = null,
        ?string $model = null,
        array $tools = [],
        ?string $key = null,
    ): PendingAgentRequest {
        $agent = new AnonymousAgent(
            agentKey: $key ?? 'anonymous',
            systemPromptText: $systemPrompt,
            agentProvider: $provider,
            agentModel: $model,
            agentTools: $tools,
        );

        return new PendingAgentRequest(
            $this->agentResolver,
            $this->agentExecutor,
            $agent,
        );
    }

    /**
     * Start building an embeddings request with optional config defaults.
     *
     * When `atlas.embeddings.provider` and `atlas.embeddings.model` are set,
     * they are applied automatically. Users can still override with ->using().
     */
    public function embeddings(): PrismProxy
    {
        $prismRequest = Prism::embeddings();

        $provider = config('atlas.embeddings.provider');
        $model = config('atlas.embeddings.model');

        if ($provider !== null && $model !== null) {
            $prismRequest = $prismRequest->using($provider, $model);
        }

        return new PrismProxy($this->pipelineRunner, $prismRequest, 'embeddings');
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
