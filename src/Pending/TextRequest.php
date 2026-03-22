<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Concerns\HasVariables;
use Atlasphp\Atlas\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\TextCompleted;
use Atlasphp\Atlas\Events\TextStarted;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Requests\TextRequest as TextRequestObject;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Support\VariableInterpolator;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

/**
 * Fluent builder for text generation, streaming, and structured output requests.
 *
 * When tools are present, terminal methods route through the executor's step loop
 * for automatic tool call handling. Without tools, calls go directly to the driver.
 */
class TextRequest implements QueueableRequest
{
    use HasMeta;
    use HasMiddleware;
    use HasQueueDispatch;
    use HasVariables;
    use NormalizesMessages;
    use ResolvesProvider;

    protected ?string $instructions = null;

    protected ?string $message = null;

    /** @var array<int, mixed> */
    protected array $messageMedia = [];

    /** @var array<int, mixed> */
    protected array $messages = [];

    protected ?int $maxTokens = null;

    protected ?float $temperature = null;

    protected ?Schema $schema = null;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    /** @var array<int, Tool|string> */
    protected array $tools = [];

    /** @var array<int, ProviderTool> */
    protected array $providerTools = [];

    protected ?int $maxSteps = 200;

    protected bool $parallelToolCalls = true;

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ?string $model,
        protected readonly ProviderRegistryContract $registry,
        protected readonly ?Application $app = null,
        protected readonly ?Dispatcher $events = null,
    ) {}

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * @param  array<int, Input>|Input  $media
     */
    public function message(string $message, array|Input $media = []): static
    {
        $this->message = $message;
        $this->messageMedia = $media instanceof Input ? [$media] : $media;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $messages
     */
    public function withMessages(array $messages): static
    {
        $this->messages = $this->normalizeMessages($messages);

        return $this;
    }

    public function withMaxTokens(int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function withTemperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function withSchema(Schema $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptions = $options;

        return $this;
    }

    /**
     * Add tools for automatic tool call handling via the executor step loop.
     *
     * Accepts Tool instances or class strings (resolved from container).
     *
     * @param  array<int, Tool|string>  $tools
     */
    public function withTools(array $tools): static
    {
        $this->tools = array_merge($this->tools, $tools);

        return $this;
    }

    /**
     * Add provider-native tools (web search, code interpreter, etc.).
     *
     * @param  array<int, ProviderTool>  $providerTools
     */
    public function withProviderTools(array $providerTools): static
    {
        $this->providerTools = array_merge($this->providerTools, $providerTools);

        return $this;
    }

    public function withMaxSteps(?int $maxSteps): static
    {
        $this->maxSteps = $maxSteps;

        return $this;
    }

    public function withParallelToolCalls(bool $parallel = true): static
    {
        $this->parallelToolCalls = $parallel;

        return $this;
    }

    public function asText(): TextResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asText');
        }

        event(new TextStarted(modality: Modality::Text, provider: $this->resolveProviderKey(), model: (string) $this->model));

        if ($this->hasTools()) {
            $response = $this->executeWithTools();

            event(new TextCompleted(modality: Modality::Text, provider: $this->resolveProviderKey(), model: (string) $this->model, usage: $response->usage));

            return $response;
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'text');

        $response = $driver->text($this->buildRequest());

        event(new TextCompleted(modality: Modality::Text, provider: $this->resolveProviderKey(), model: (string) $this->model, usage: $response->usage));

        return $response;
    }

    public function asStream(): StreamResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asStream');
        }

        event(new TextStarted(modality: Modality::Stream, provider: $this->resolveProviderKey(), model: (string) $this->model));

        if ($this->hasTools()) {
            throw new AtlasException('Streaming with tools is not yet supported. Use asText() for tool-enabled requests.');
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'stream');

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        $response = $driver->stream($this->buildRequest());

        // TextCompleted fires after the stream is fully consumed, not when the
        // response object is created. The then() callback runs at the end of
        // StreamResponse::getIterator() after all chunks have been yielded.
        $response->then(function () use ($response, $provider, $model) {
            event(new TextCompleted(modality: Modality::Stream, provider: $provider, model: $model, usage: $response->getUsage()));
        });

        return $response;
    }

    public function asStructured(): StructuredResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asStructured');
        }

        event(new TextStarted(modality: Modality::Structured, provider: $this->resolveProviderKey(), model: (string) $this->model));

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'structured');

        $response = $driver->structured($this->buildRequest());

        event(new TextCompleted(modality: Modality::Structured, provider: $this->resolveProviderKey(), model: (string) $this->model, usage: $response->usage));

        return $response;
    }

    protected function hasTools(): bool
    {
        return $this->tools !== [] || $this->providerTools !== [];
    }

    protected function executeWithTools(): TextResponse
    {
        $resolvedTools = $this->resolveTools();

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'text');

        // ToolRegistry is a stateless value object (immutable map) and ToolExecutor
        // is a thin wrapper with no external dependencies — direct instantiation is
        // intentional here to avoid unnecessary container overhead.
        $toolRegistry = new ToolRegistry($resolvedTools);
        $toolExecutor = new ToolExecutor($toolRegistry);

        $executor = new AgentExecutor(
            driver: $driver,
            toolExecutor: $toolExecutor,
            events: $this->events ?? app(Dispatcher::class),
            middlewareStack: $this->app?->make(MiddlewareStack::class) ?? app(MiddlewareStack::class),
        );

        $request = $this->buildRequestWithTools($resolvedTools);

        $result = $executor->execute(
            request: $request,
            maxSteps: $this->maxSteps,
            parallelToolCalls: $this->parallelToolCalls,
            meta: $this->meta,
            agentKey: null,
        );

        return new TextResponse(
            text: $result->text,
            usage: $result->usage,
            finishReason: $result->finishReason,
            toolCalls: $result->allToolCalls(),
            reasoning: $result->reasoning,
            steps: $result->steps,
            meta: $result->meta,
        );
    }

    /**
     * Resolve tool class strings from the container.
     *
     * @return array<int, Tool>
     */
    protected function resolveTools(): array
    {
        return array_map(function (Tool|string $tool): Tool {
            if (is_string($tool)) {
                return app($tool);
            }

            return $tool;
        }, $this->tools);
    }

    public function buildRequest(): TextRequestObject
    {
        return $this->buildRequestWithTools($this->resolveTools());
    }

    /**
     * Build the request with pre-resolved tools to avoid double resolution.
     *
     * @param  array<int, Tool>  $resolvedTools
     */
    protected function buildRequestWithTools(array $resolvedTools): TextRequestObject
    {
        return new TextRequestObject(
            model: $this->model ?? '',
            instructions: $this->interpolate($this->instructions),
            message: $this->interpolateMessages
                ? $this->interpolate($this->message)
                : $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->interpolateMessages
                ? $this->interpolateMessageArray($this->messages)
                : $this->messages,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: array_map(fn (Tool $t) => $t->toDefinition(), $resolvedTools),
            providerTools: $this->providerTools,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }

    /**
     * Serialize all properties needed to rebuild this request in a queue worker.
     *
     * @return array<string, mixed>
     */
    public function toQueuePayload(): array
    {
        return [
            'provider' => $this->resolveProviderKey(),
            'model' => $this->resolveModelKey(),
            'instructions' => $this->instructions,
            'message' => $this->message,
            'messageMedia' => $this->messageMedia,
            'messages' => $this->messages,
            'maxTokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'tools' => array_map(fn (Tool|string $tool): string => is_string($tool) ? $tool : $tool::class, $this->tools),
            'providerTools' => $this->providerTools,
            'maxSteps' => $this->maxSteps,
            'parallelToolCalls' => $this->parallelToolCalls,
            'schema' => $this->schema !== null ? [
                'name' => $this->schema->name(),
                'description' => $this->schema->description(),
                'data' => $this->schema->toArray(),
            ] : null,
            'providerOptions' => $this->providerOptions,
            'middleware' => array_map(fn (mixed $m): string => is_string($m) ? $m : $m::class, $this->middleware),
            'meta' => $this->meta,
            'variables' => $this->variables,
            'interpolate_messages' => $this->interpolateMessages,
        ];
    }

    /**
     * Rebuild this request from a queue payload and execute the given terminal method.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asText', 'asStream', 'asStructured')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting stream chunks
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed {
        $request = Atlas::text($payload['provider'], $payload['model']);

        if ($payload['instructions'] !== null) {
            $request->instructions($payload['instructions']);
        }

        if ($payload['message'] !== null) {
            $request->message($payload['message'], $payload['messageMedia'] ?? []);
        }

        if (! empty($payload['messages'])) {
            $request->withMessages($payload['messages']);
        }

        if ($payload['maxTokens'] !== null) {
            $request->withMaxTokens($payload['maxTokens']);
        }

        if ($payload['temperature'] !== null) {
            $request->withTemperature($payload['temperature']);
        }

        if (! empty($payload['tools'])) {
            $request->withTools($payload['tools']);
        }

        if (! empty($payload['providerTools'])) {
            $request->withProviderTools($payload['providerTools']);
        }

        if (array_key_exists('maxSteps', $payload)) {
            $request->withMaxSteps($payload['maxSteps']);
        }

        if (array_key_exists('parallelToolCalls', $payload)) {
            $request->withParallelToolCalls($payload['parallelToolCalls']);
        }

        if (! empty($payload['providerOptions'])) {
            $request->withProviderOptions($payload['providerOptions']);
        }

        if (! empty($payload['middleware'])) {
            $request->withMiddleware($payload['middleware']);
        }

        if (! empty($payload['schema'])) {
            $request->withSchema(new Schema(
                $payload['schema']['name'],
                $payload['schema']['description'],
                $payload['schema']['data'],
            ));
        }

        $meta = $payload['meta'] ?? [];

        if ($executionId !== null) {
            $meta['_execution_id'] = $executionId;
        }

        if (! empty($meta)) {
            $request->withMeta($meta);
        }

        if (! empty($payload['variables'])) {
            $request->withVariables($payload['variables']);
        }

        if ($payload['interpolate_messages'] ?? false) {
            $request->withMessageInterpolation();
        }

        $result = match ($terminal) {
            'asText' => $request->asText(),
            'asStream' => $request->asStream(),
            'asStructured' => $request->asStructured(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };

        if ($result instanceof StreamResponse && $broadcastChannel !== null) {
            $result->broadcastOn($broadcastChannel);
        }

        return $result;
    }

    /**
     * Interpolate variable placeholders in a messages array.
     *
     * @param  array<int, mixed>  $messages
     * @return array<int, mixed>
     */
    protected function interpolateMessageArray(array $messages): array
    {
        $resolved = $this->resolveVariables();

        return array_map(function (mixed $message) use ($resolved): mixed {
            if ($message instanceof UserMessage && $message->content !== '') {
                return new UserMessage(
                    VariableInterpolator::interpolate($message->content, $resolved),
                    $message->media,
                );
            }

            if ($message instanceof AssistantMessage && $message->content !== null) {
                return new AssistantMessage(
                    VariableInterpolator::interpolate($message->content, $resolved),
                    $message->toolCalls,
                    $message->reasoning,
                );
            }

            if ($message instanceof SystemMessage) {
                return new SystemMessage(
                    VariableInterpolator::interpolate($message->content, $resolved),
                );
            }

            return $message;
        }, $messages);
    }

    /**
     * Resolve the provider as a string key for queue serialization.
     */
    protected function resolveProviderKey(): string
    {
        return $this->provider instanceof Provider ? $this->provider->value : (string) $this->provider;
    }

    /**
     * Resolve the model as a string key for queue serialization.
     */
    protected function resolveModelKey(): string
    {
        return (string) $this->model;
    }

    /**
     * Get metadata for the execution record when queuing.
     *
     * @return array<string, mixed>
     */
    protected function getQueueMeta(): array
    {
        return $this->meta;
    }
}
