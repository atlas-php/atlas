<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Support\VariableInterpolator;
use Atlasphp\Atlas\Support\VariableRegistry;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

/**
 * Fluent builder for agent execution requests.
 *
 * Resolves an agent by key, builds a TextRequest with variable interpolation,
 * and dispatches through the AgentExecutor (with tools) or directly to the
 * driver (without tools). Supports runtime overrides for all agent config.
 */
class AgentRequest
{
    use NormalizesMessages;

    // ─── Runtime overrides ──────────────────────────────────────────

    protected ?string $instructionsOverride = null;

    protected ?string $message = null;

    /** @var array<int, Input> */
    protected array $messageMedia = [];

    /** @var array<int, mixed> */
    protected array $messages = [];

    /** @var array<string, mixed> */
    protected array $variables = [];

    /** @var array<int, Tool|string> */
    protected array $additionalTools = [];

    /** @var array<int, ProviderTool> */
    protected array $additionalProviderTools = [];

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected ?Schema $schema = null;

    // Provider/model override
    protected Provider|string|null $providerOverride = null;

    protected ?string $modelOverride = null;

    // Config overrides
    protected ?int $maxTokensOverride = null;

    protected ?float $temperatureOverride = null;

    protected ?int $maxStepsOverride = null;

    protected ?bool $parallelToolCallsOverride = null;

    /** @var array<string, mixed> */
    protected array $providerOptionsOverride = [];

    // Conversation support — stored here, transferred to agent on resolve
    protected ?Model $conversationOwner = null;

    protected ?Model $messageAuthor = null;

    protected ?int $conversationId = null;

    protected ?int $runtimeMessageLimit = null;

    protected bool $respondMode = false;

    protected bool $retryMode = false;

    public function __construct(
        protected readonly string $key,
        protected readonly AgentRegistry $agentRegistry,
        protected readonly ProviderRegistryContract $providerRegistry,
        protected readonly VariableRegistry $variableRegistry,
        protected readonly Application $app,
        protected readonly Dispatcher $events,
    ) {}

    // ─── Primary ────────────────────────────────────────────────────

    /**
     * Override the agent's system instructions.
     */
    public function instructions(string $directive): static
    {
        $this->instructionsOverride = $directive;

        return $this;
    }

    /**
     * Set the user message to send.
     *
     * @param  array<int, Input>|Input  $media
     */
    public function message(string $text, array|Input $media = []): static
    {
        $this->message = $text;
        $this->messageMedia = $media instanceof Input ? [$media] : $media;

        return $this;
    }

    // ─── Context ────────────────────────────────────────────────────

    /**
     * Provide conversation history messages.
     *
     * @param  array<int, mixed>  $messages
     */
    public function withMessages(array $messages): static
    {
        $this->messages = $this->normalizeMessages($messages);

        return $this;
    }

    /**
     * Set per-call variable overrides (highest priority in interpolation).
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): static
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * Set metadata passed through to executor and tool context.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    // ─── Tools ──────────────────────────────────────────────────────

    /**
     * Add tools in addition to the agent's configured tools.
     *
     * @param  array<int, Tool|string>  $tools
     */
    public function withTools(array $tools): static
    {
        $this->additionalTools = $tools;

        return $this;
    }

    /**
     * Add provider tools in addition to the agent's configured provider tools.
     *
     * @param  array<int, ProviderTool>  $providerTools
     */
    public function withProviderTools(array $providerTools): static
    {
        $this->additionalProviderTools = $providerTools;

        return $this;
    }

    // ─── Config overrides ───────────────────────────────────────────

    /**
     * Override the agent's provider and model.
     */
    public function withProvider(Provider|string $provider, string $model): static
    {
        $this->providerOverride = $provider;
        $this->modelOverride = $model;

        return $this;
    }

    /**
     * Override the agent's max tokens.
     */
    public function withMaxTokens(int $tokens): static
    {
        $this->maxTokensOverride = $tokens;

        return $this;
    }

    /**
     * Override the agent's temperature.
     */
    public function withTemperature(float $temp): static
    {
        $this->temperatureOverride = $temp;

        return $this;
    }

    /**
     * Override the agent's max steps in the tool loop.
     */
    public function withMaxSteps(int $steps): static
    {
        $this->maxStepsOverride = $steps;

        return $this;
    }

    /**
     * Override parallel tool call execution for this call.
     */
    public function withParallelToolCalls(bool $parallel = true): static
    {
        $this->parallelToolCallsOverride = $parallel;

        return $this;
    }

    /**
     * Set a structured output schema.
     */
    public function withSchema(Schema $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Override provider-specific options.
     *
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptionsOverride = $options;

        return $this;
    }

    // ─── Conversation Support ───────────────────────────────────────

    /**
     * Set the conversation owner (creates/finds conversation for this model).
     */
    public function for(Model $owner): static
    {
        $this->conversationOwner = $owner;

        return $this;
    }

    /**
     * Set the author of the incoming message.
     */
    public function asUser(Model $author): static
    {
        $this->messageAuthor = $author;

        return $this;
    }

    /**
     * Join an existing conversation by ID.
     */
    public function forConversation(int $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    /**
     * Respond to an existing conversation without a new user message.
     */
    public function respond(): static
    {
        $this->respondMode = true;

        return $this;
    }

    /**
     * Retry the last assistant response in the conversation.
     */
    public function retry(): static
    {
        $this->retryMode = true;

        return $this;
    }

    /**
     * Override the message history limit for this call.
     */
    public function withMessageLimit(int $limit): static
    {
        $this->runtimeMessageLimit = $limit;

        return $this;
    }

    // ─── Terminal ───────────────────────────────────────────────────

    /**
     * Execute the agent and return a text response.
     */
    public function asText(): TextResponse
    {
        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, $tools);

        return $this->dispatchAgentMiddleware($agent, $request, $tools, function (AgentContext $ctx) use ($driver, $agent, $tools) {
            if ($tools === []) {
                return $driver->text($ctx->request);
            }

            $result = $this->executeWithTools($driver, $ctx->request, $agent, $tools);

            return new TextResponse(
                text: $result->text,
                usage: $result->usage,
                finishReason: $result->finishReason,
                toolCalls: $result->allToolCalls(),
                reasoning: $result->reasoning,
                steps: $result->steps,
                meta: array_merge($result->meta, [
                    'conversation_id' => $result->conversationId,
                    'execution_id' => $result->executionId,
                ]),
            );
        });
    }

    /**
     * Execute the agent and return a stream response.
     */
    public function asStream(): StreamResponse
    {
        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, $tools);

        return $this->dispatchAgentMiddleware($agent, $request, $tools, function (AgentContext $ctx) use ($driver, $agent, $tools) {
            if ($tools === []) {
                return $driver->stream($ctx->request);
            }

            // Streaming with tools falls back to non-streaming, wraps result as chunks
            $result = $this->executeWithTools($driver, $ctx->request, $agent, $tools);

            return new StreamResponse($this->resultToChunks($result));
        });
    }

    /**
     * Execute the agent and return a structured response.
     */
    public function asStructured(): StructuredResponse
    {
        $agent = $this->resolveAgent();
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, []);

        return $this->dispatchAgentMiddleware($agent, $request, [], function (AgentContext $ctx) use ($driver) {
            return $driver->structured($ctx->request);
        });
    }

    // ─── Internal: Resolution ───────────────────────────────────────

    /**
     * Resolve the agent instance and transfer conversation state.
     */
    protected function resolveAgent(): Agent
    {
        $agent = $this->agentRegistry->resolve($this->key);

        $this->transferConversationState($agent);

        return $agent;
    }

    /**
     * Transfer conversation state from the request to the agent.
     */
    protected function transferConversationState(Agent $agent): void
    {
        $usesConversations = in_array(HasConversations::class, class_uses_recursive($agent), true);

        if (! $usesConversations) {
            return;
        }

        if ($this->conversationOwner !== null) {
            $agent->for($this->conversationOwner); // @phpstan-ignore method.notFound
        }

        if ($this->messageAuthor !== null) {
            $agent->asUser($this->messageAuthor); // @phpstan-ignore method.notFound
        }

        if ($this->conversationId !== null) {
            $agent->forConversation($this->conversationId); // @phpstan-ignore method.notFound
        }

        if ($this->runtimeMessageLimit !== null) {
            $agent->withMessageLimit($this->runtimeMessageLimit); // @phpstan-ignore method.notFound
        }

        if ($this->respondMode) {
            $agent->respond(); // @phpstan-ignore method.notFound
        }

        if ($this->retryMode) {
            $agent->retry(); // @phpstan-ignore method.notFound
        }
    }

    /**
     * Resolve the driver from provider override, agent config, or defaults.
     */
    protected function resolveDriver(Agent $agent): Driver
    {
        $provider = $this->providerOverride
            ?? $agent->provider()
            ?? config('atlas.defaults.text.provider');

        if ($provider === null) {
            throw AtlasException::missingDefault('agent');
        }

        $key = $provider instanceof Provider ? $provider->value : $provider;

        return $this->providerRegistry->resolve($key);
    }

    /**
     * Resolve all tools (agent + runtime additions) into Tool instances.
     *
     * @return array<int, Tool>
     */
    protected function resolveTools(Agent $agent): array
    {
        $raw = array_merge($agent->tools(), $this->additionalTools);
        $tools = [];

        foreach ($raw as $item) {
            if ($item instanceof Tool) {
                $tools[] = $item;
            } elseif (is_string($item) && class_exists($item)) {
                $tools[] = $this->app->make($item);
            } elseif (is_string($item)) {
                throw new AtlasException("Tool class [{$item}] does not exist.");
            }
        }

        return $tools;
    }

    /**
     * Resolve provider tools (agent + runtime additions).
     *
     * @return array<int, ProviderTool>
     */
    protected function resolveProviderTools(Agent $agent): array
    {
        return array_merge($agent->providerTools(), $this->additionalProviderTools);
    }

    // ─── Internal: Build Request ────────────────────────────────────

    /**
     * Build the immutable TextRequest from agent config and runtime overrides.
     *
     * @param  array<int, Tool>  $tools
     */
    protected function buildRequest(Agent $agent, array $tools): TextRequest
    {
        // Resolve instructions with variable interpolation
        $rawInstructions = $this->instructionsOverride ?? $agent->instructions();
        $instructions = null;

        if ($rawInstructions !== null) {
            $mergedVariables = $this->variableRegistry->merge($this->variables, $this->meta);

            if (VariableInterpolator::hasPlaceholders($rawInstructions)) {
                $instructions = VariableInterpolator::interpolate($rawInstructions, $mergedVariables);
            } else {
                $instructions = $rawInstructions;
            }
        }

        // Resolve model
        $model = $this->modelOverride
            ?? $agent->model()
            ?? config('atlas.defaults.text.model');

        if ($model === null || $model === '') {
            throw AtlasException::missingDefault('agent model');
        }

        // Build tool definitions
        $toolDefinitions = array_map(
            fn (Tool $tool) => $tool->toDefinition(),
            $tools,
        );

        // Resolve provider tools
        $providerTools = $this->resolveProviderTools($agent);

        return new TextRequest(
            model: $model,
            instructions: $instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->messages,
            maxTokens: $this->maxTokensOverride ?? $agent->maxTokens(),
            temperature: $this->temperatureOverride ?? $agent->temperature(),
            schema: $this->schema,
            tools: $toolDefinitions,
            providerTools: $providerTools,
            providerOptions: $this->providerOptionsOverride !== []
                ? $this->providerOptionsOverride
                : $agent->providerOptions(),
            meta: $this->meta,
        );
    }

    // ─── Internal: Execution ────────────────────────────────────────

    /**
     * Execute the agent through the AgentExecutor tool loop.
     *
     * @param  array<int, Tool>  $tools
     */
    protected function executeWithTools(
        Driver $driver,
        TextRequest $request,
        Agent $agent,
        array $tools,
    ): ExecutorResult {
        $toolRegistry = new ToolRegistry($tools);
        $toolExecutor = new ToolExecutor($toolRegistry);

        $middlewareStack = $this->app->make(MiddlewareStack::class);

        $agentExecutor = new AgentExecutor(
            driver: $driver,
            toolExecutor: $toolExecutor,
            events: $this->events,
            middlewareStack: $middlewareStack,
        );

        $maxSteps = $this->maxStepsOverride ?? $agent->maxSteps();

        $parallelToolCalls = $this->parallelToolCallsOverride ?? $agent->parallelToolCalls();

        return $agentExecutor->execute(
            request: $request,
            maxSteps: $maxSteps,
            parallelToolCalls: $parallelToolCalls,
            meta: $this->meta,
        );
    }

    /**
     * Dispatch execution through agent middleware.
     *
     * @param  array<int, Tool>  $tools
     */
    protected function dispatchAgentMiddleware(
        Agent $agent,
        TextRequest $request,
        array $tools,
        \Closure $destination,
    ): mixed {
        $context = new AgentContext(
            request: $request,
            agent: $agent,
            messages: $this->messages,
            tools: $tools,
            meta: $this->meta,
        );

        $middleware = config('atlas.middleware.agent', []);

        if ($middleware === []) {
            return $destination($context);
        }

        /** @var MiddlewareStack $stack */
        $stack = $this->app->make(MiddlewareStack::class);

        return $stack->run(
            $context,
            $middleware,
            fn (AgentContext $ctx) => $destination($ctx),
        );
    }

    /**
     * Convert an ExecutorResult into a generator of StreamChunks.
     */
    protected function resultToChunks(ExecutorResult $result): \Generator
    {
        yield new StreamChunk(
            type: ChunkType::Text,
            text: $result->text,
        );

        yield new StreamChunk(
            type: ChunkType::Done,
        );
    }
}
