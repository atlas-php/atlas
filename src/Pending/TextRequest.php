<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ExecutionContext;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Pending\Concerns\ConvertsResultToChunks;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Pending\Concerns\HasRequestConfig;
use Atlasphp\Atlas\Pending\Concerns\HasVariables;
use Atlasphp\Atlas\Pending\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Queue\Contracts\QueueableRequest;
use Atlasphp\Atlas\Queue\PendingExecution;
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
use Illuminate\Support\Str;

/**
 * Fluent builder for text generation, streaming, and structured output requests.
 *
 * When tools are present, terminal methods route through the executor's step loop
 * for automatic tool call handling. Without tools, calls go directly to the driver.
 */
class TextRequest implements QueueableRequest
{
    use ConvertsResultToChunks;
    use HasMeta;
    use HasMiddleware;
    use HasProviderOptions;
    use HasQueueDispatch;
    use HasRequestConfig;
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

    /** @var array<int, Tool|string> */
    protected array $tools = [];

    /** @var array<int, ProviderTool> */
    protected array $providerTools = [];

    protected ?int $maxSteps = 200;

    protected bool $concurrent = false;

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

    public function withConcurrent(bool $concurrent = true): static
    {
        $this->concurrent = $concurrent;

        return $this;
    }

    public function asText(): TextResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asText');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Text, provider: $provider, model: $model, traceId: $traceId));

        try {
            if ($this->hasTools()) {
                $result = $this->executeWithTools($provider, $model, $traceId);
                $response = $result->toTextResponse();
            } else {
                $driver = $this->resolveDriver();
                $this->ensureCapability($driver, 'text');
                $response = $driver->text($this->buildRequest());
            }
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Text, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Text, provider: $provider, model: $model, usage: $response->usage, traceId: $traceId));

        return $response;
    }

    public function asStream(): StreamResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asStream');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Stream, provider: $provider, model: $model, traceId: $traceId));

        try {
            if ($this->hasTools()) {
                // Atlas tools require the executor loop — stream the result
                $result = $this->executeWithTools($provider, $model, $traceId);
                $response = new StreamResponse($this->resultToChunks($result));
            } else {
                // No Atlas tools — stream directly from the provider
                // (provider tools are handled server-side in the stream)
                $driver = $this->resolveDriver();
                $this->ensureCapability($driver, 'stream');
                $response = $driver->stream($this->buildRequest());
            }
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Stream, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        // ModalityCompleted fires after the stream finishes, whether by success or error.
        // Using onFinally() ensures the event always pairs with ModalityStarted.
        $response->onFinally(function () use ($response, $provider, $model, $traceId) {
            event(new ModalityCompleted(modality: Modality::Stream, provider: $provider, model: $model, usage: $response->getUsage(), traceId: $traceId));
        });

        return $response;
    }

    public function asStructured(): StructuredResponse|PendingExecution
    {
        $traceId = (string) Str::uuid();

        if ($this->queued) {
            return $this->dispatchToQueue('asStructured');
        }

        $provider = $this->resolveProviderKey();
        $model = (string) $this->model;

        event(new ModalityStarted(modality: Modality::Structured, provider: $provider, model: $model, traceId: $traceId));

        try {
            $driver = $this->resolveDriver();
            $this->ensureCapability($driver, 'structured');
            $response = $driver->structured($this->buildRequest());
        } catch (\Throwable $e) {
            event(new ModalityCompleted(modality: Modality::Structured, provider: $provider, model: $model, traceId: $traceId));

            throw $e;
        }

        event(new ModalityCompleted(modality: Modality::Structured, provider: $provider, model: $model, usage: $response->usage, traceId: $traceId));

        return $response;
    }

    /**
     * Whether Atlas tools requiring the executor loop are present.
     *
     * Provider tools are handled server-side by the driver and do not
     * need the executor step loop.
     */
    protected function hasTools(): bool
    {
        return $this->tools !== [];
    }

    protected function executeWithTools(?string $provider = null, ?string $model = null, ?string $traceId = null): ExecutorResult
    {
        $resolvedTools = $this->resolveTools();

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'text');

        $executor = AgentExecutor::forTools(
            driver: $driver,
            tools: $resolvedTools,
            events: $this->events ?? app(Dispatcher::class),
            middlewareStack: $this->app?->make(MiddlewareStack::class) ?? app(MiddlewareStack::class),
        );

        $request = $this->buildRequestWithTools($resolvedTools);

        return $executor->execute(
            request: $request,
            maxSteps: $this->maxSteps,
            concurrent: $this->concurrent,
            meta: $this->meta,
            context: new ExecutionContext(
                provider: $provider,
                model: $model,
                traceId: $traceId,
                broadcastChannel: $this->broadcastChannel,
            ),
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
            'concurrent' => $this->concurrent,
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

        if (array_key_exists('concurrent', $payload)) {
            $request->withConcurrent($payload['concurrent']);
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

        static::applyMeta($request, $payload, $executionId);
        static::applyVariables($request, $payload);

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
}
