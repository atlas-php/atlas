<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\HasQueueDispatch;
use Atlasphp\Atlas\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Queue\Contracts\QueueableRequest;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Requests\TextRequest as TextRequestObject;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Events\Dispatcher;

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

        if ($this->hasTools()) {
            return $this->executeWithTools();
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'text');

        return $driver->text($this->buildRequest());
    }

    public function asStream(): StreamResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asStream');
        }

        if ($this->hasTools()) {
            throw new AtlasException('Streaming with tools is not yet supported. Use asText() for tool-enabled requests.');
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'stream');

        return $driver->stream($this->buildRequest());
    }

    public function asStructured(): StructuredResponse|PendingExecution
    {
        if ($this->queued) {
            return $this->dispatchToQueue('asStructured');
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'structured');

        return $driver->structured($this->buildRequest());
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

        $toolRegistry = new ToolRegistry($resolvedTools);
        $toolExecutor = new ToolExecutor($toolRegistry);

        $executor = new AgentExecutor(
            driver: $driver,
            toolExecutor: $toolExecutor,
            events: app(Dispatcher::class),
            middlewareStack: app(MiddlewareStack::class),
        );

        $request = $this->buildRequestWithTools($resolvedTools);

        $result = $executor->execute(
            request: $request,
            maxSteps: $this->maxSteps,
            parallelToolCalls: $this->parallelToolCalls,
            meta: $this->meta,
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
            instructions: $this->instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->messages,
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
            'meta' => $this->meta,
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

        if ($terminal === 'asStream' && $broadcastChannel !== null) {
            $stream = $request->asStream()->broadcastOn($broadcastChannel);

            foreach ($stream as $chunk) {
                // Iterating triggers broadcasting
            }

            return $stream;
        }

        return match ($terminal) {
            'asText' => $request->asText(),
            'asStream' => $request->asStream(),
            'asStructured' => $request->asStructured(),
            default => throw new \InvalidArgumentException("Unknown terminal method: {$terminal}"),
        };
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
