<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Concerns\HasVariables;
use Atlasphp\Atlas\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Broadcasting\Channel;
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
class AgentRequest implements QueueableRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasQueueDispatch;
    use HasVariables;
    use NormalizesMessages;

    // ─── Runtime overrides ──────────────────────────────────────────

    protected ?string $instructionsOverride = null;

    protected ?string $message = null;

    /** @var array<int, Input> */
    protected array $messageMedia = [];

    /** @var array<int, mixed> */
    protected array $messages = [];

    /** @var array<int, Tool|string> */
    protected array $additionalTools = [];

    /** @var array<int, ProviderTool> */
    protected array $additionalProviderTools = [];

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
    public function asText(): TextResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asText');
        }

        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, $tools);

        $result = $this->dispatchAgentMiddleware($agent, $request, $tools, function (AgentContext $ctx) use ($driver, $agent) {
            if ($ctx->tools === []) {
                return $driver->text($ctx->request);
            }

            return $this->executeWithTools($driver, $ctx->request, $agent, $ctx->tools, $ctx->meta);
        });

        if ($result instanceof TextResponse) {
            return $result;
        }

        /** @var ExecutorResult $result */
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
    }

    /**
     * Execute the agent and return a stream response.
     */
    public function asStream(): StreamResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asStream');
        }

        $agent = $this->resolveAgent();
        $tools = $this->resolveTools($agent);
        $driver = $this->resolveDriver($agent);
        $request = $this->buildRequest($agent, $tools);

        $result = $this->dispatchAgentMiddleware($agent, $request, $tools, function (AgentContext $ctx) use ($driver, $agent) {
            if ($ctx->tools === []) {
                return $driver->stream($ctx->request);
            }

            return $this->executeWithTools($driver, $ctx->request, $agent, $ctx->tools, $ctx->meta);
        });

        $stream = $result instanceof StreamResponse
            ? $result
            : new StreamResponse($this->resultToChunks($result));

        // Pipe broadcast channel to the stream response
        if ($this->broadcastChannel !== null) {
            $stream->broadcastOn($this->broadcastChannel);
        }

        return $stream;
    }

    /**
     * Execute the agent and return a structured response.
     */
    public function asStructured(): StructuredResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asStructured');
        }

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

        $key = Provider::normalize($provider);

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
        $instructions = $this->interpolate($rawInstructions);

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
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }

    // ─── Internal: Execution ────────────────────────────────────────

    /**
     * Execute the agent through the AgentExecutor tool loop.
     *
     * @param  array<int, Tool>  $tools
     * @param  array<string, mixed>  $meta
     */
    protected function executeWithTools(
        Driver $driver,
        TextRequest $request,
        Agent $agent,
        array $tools,
        array $meta = [],
    ): ExecutorResult {
        // Rebuild tool definitions from the actual tools array — middleware
        // may have added tools (e.g. WireMemory) after the request was built.
        $request = $request->withReplacedTools(array_map(
            fn (Tool $tool) => $tool->toDefinition(),
            $tools,
        ));

        // ToolRegistry is a stateless value object (immutable map) and ToolExecutor
        // is a thin wrapper with no external dependencies — direct instantiation is
        // intentional here to avoid unnecessary container overhead.
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
            meta: $meta,
            agentKey: $agent->key(),
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
     *
     * Yields the text in small segments to simulate streaming for tool-based
     * agent executions. Each segment is broadcast as a StreamChunkReceived
     * event, giving the UI a word-by-word typing effect.
     */
    protected function resultToChunks(ExecutorResult $result): \Generator
    {
        if ($result->text !== '') {
            // Split on word boundaries, preserving whitespace
            $segments = preg_split('/(?<=\s)/', $result->text, -1, PREG_SPLIT_NO_EMPTY) ?: [$result->text];

            foreach ($segments as $segment) {
                yield new StreamChunk(
                    type: ChunkType::Text,
                    text: $segment,
                );

                // Small delay between chunks for visual effect
                usleep(15_000);
            }
        }

        yield new StreamChunk(
            type: ChunkType::Done,
        );
    }

    // ─── Queue Support ─────────────────────────────────────────────

    /**
     * Serialize all properties needed to rebuild this request in a queue worker.
     *
     * @return array<string, mixed>
     */
    public function toQueuePayload(): array
    {
        return [
            'key' => $this->key,
            'message' => $this->message,
            'instructions' => $this->instructionsOverride,
            'variables' => $this->variables,
            'meta' => $this->meta,
            'provider' => $this->providerOverride !== null
                ? Provider::normalize($this->providerOverride)
                : null,
            'model' => $this->modelOverride,
            'max_tokens' => $this->maxTokensOverride,
            'temperature' => $this->temperatureOverride,
            'max_steps' => $this->maxStepsOverride,
            'parallel_tool_calls' => $this->parallelToolCallsOverride,
            'provider_options' => $this->providerOptionsOverride,
            'conversation_id' => $this->conversationId,
            'owner_type' => $this->conversationOwner?->getMorphClass(),
            'owner_id' => $this->conversationOwner?->getKey(),
            'author_type' => $this->messageAuthor?->getMorphClass(),
            'author_id' => $this->messageAuthor?->getKey(),
            'message_limit' => $this->runtimeMessageLimit,
            'respond_mode' => $this->respondMode,
            'retry_mode' => $this->retryMode,
            'message_media' => array_map(fn (Input $input) => [
                'class' => $input::class,
                'mime' => $input->mimeType(),
                'base64' => $input->isBase64() ? $input->data() : null,
                'url' => $input->isUrl() ? $input->url() : null,
                'storage_path' => $input->isStorage() ? $input->storagePath() : null,
                'storage_disk' => $input->isStorage() ? $input->storageDisk() : null,
                'path' => $input->isPath() ? $input->path() : null,
                'file_id' => $input->isFileId() ? $input->fileId() : null,
            ], $this->messageMedia),
            'middleware' => array_map(fn (mixed $m): string => is_string($m) ? $m : $m::class, $this->middleware),
        ];
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asText', 'asStream')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::agent($payload['key']);

        if ($payload['message'] !== null) {
            $media = self::restoreMedia($payload['message_media'] ?? []);
            $request->message($payload['message'], $media);
        }

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if (! empty($payload['variables'])) {
            $request->withVariables($payload['variables']);
        }

        $meta = $payload['meta'] ?? [];

        if ($executionId !== null) {
            $meta['_execution_id'] = $executionId;
        }

        if (! empty($meta)) {
            $request->withMeta($meta);
        }

        if ($payload['provider'] !== null && $payload['model'] !== null) {
            $request->withProvider($payload['provider'], $payload['model']);
        }

        if ($payload['max_tokens'] !== null) {
            $request->withMaxTokens($payload['max_tokens']);
        }

        if ($payload['temperature'] !== null) {
            $request->withTemperature($payload['temperature']);
        }

        if ($payload['max_steps'] !== null) {
            $request->withMaxSteps($payload['max_steps']);
        }

        if ($payload['parallel_tool_calls'] !== null) {
            $request->withParallelToolCalls($payload['parallel_tool_calls']);
        }

        if (! empty($payload['provider_options'])) {
            $request->withProviderOptions($payload['provider_options']);
        }

        if (! empty($payload['middleware'])) {
            $request->withMiddleware($payload['middleware']);
        }

        // Restore conversation state
        if ($payload['owner_type'] !== null && $payload['owner_id'] !== null) {
            $owner = $payload['owner_type']::findOrFail($payload['owner_id']);
            $request->for($owner);
        }

        if ($payload['author_type'] !== null && $payload['author_id'] !== null) {
            $author = $payload['author_type']::findOrFail($payload['author_id']);
            $request->asUser($author);
        }

        if ($payload['conversation_id'] !== null) {
            $request->forConversation($payload['conversation_id']);
        }

        if ($payload['message_limit'] !== null) {
            $request->withMessageLimit($payload['message_limit']);
        }

        if ($payload['respond_mode'] ?? false) {
            $request->respond();
        }

        if ($payload['retry_mode'] ?? false) {
            $request->retry();
        }

        if ($broadcastChannel !== null) {
            $request->broadcastOn($broadcastChannel);
        }

        return match ($terminal) {
            'asText' => $request->asText(),
            'asStream' => $request->asStream(),
            'asStructured' => $request->asStructured(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
    }

    /**
     * Rebuild Input objects from serialized media array.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, Input>
     */
    protected static function restoreMedia(array $items): array
    {
        $media = [];

        foreach ($items as $item) {
            $input = self::restoreMediaItem($item);

            if ($input !== null) {
                $media[] = $input;
            }
        }

        return $media;
    }

    /**
     * Restore a single media input from its serialized form.
     *
     * @param  array<string, mixed>  $item
     */
    protected static function restoreMediaItem(array $item): ?Input
    {
        /** @var class-string<Input> $class */
        $class = $item['class'];

        if (! is_subclass_of($class, Input::class)) {
            return null;
        }

        if ($item['base64'] !== null && method_exists($class, 'fromBase64')) {
            return $class::fromBase64($item['base64'], $item['mime']);
        }

        if ($item['storage_path'] !== null && method_exists($class, 'fromStorage')) {
            return $class::fromStorage($item['storage_path'], $item['storage_disk']);
        }

        if ($item['url'] !== null && method_exists($class, 'fromUrl')) {
            return $class::fromUrl($item['url']);
        }

        if ($item['path'] !== null && method_exists($class, 'fromPath')) {
            return $class::fromPath($item['path']);
        }

        if ($item['file_id'] !== null && method_exists($class, 'fromFileId')) {
            return $class::fromFileId($item['file_id']);
        }

        return null;
    }

    /**
     * Resolve the provider as a string key for queue serialization.
     */
    protected function resolveProviderKey(): string
    {
        if ($this->providerOverride !== null) {
            return Provider::normalize($this->providerOverride);
        }

        $agent = $this->agentRegistry->resolve($this->key);
        $provider = $agent->provider();

        if ($provider !== null) {
            return Provider::normalize($provider);
        }

        return (string) config('atlas.defaults.text.provider', 'openai');
    }

    /**
     * Resolve the model as a string key for queue serialization.
     */
    protected function resolveModelKey(): string
    {
        if ($this->modelOverride !== null) {
            return $this->modelOverride;
        }

        $agent = $this->agentRegistry->resolve($this->key);

        return $agent->model() ?? (string) config('atlas.defaults.text.model', '');
    }
}
