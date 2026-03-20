<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Requests\TextRequest as TextRequestObject;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Fluent builder for text generation, streaming, and structured output requests.
 *
 * When tools are present, terminal methods route through the executor's step loop
 * for automatic tool call handling. Without tools, calls go directly to the driver.
 */
class TextRequest
{
    use HasMeta;
    use HasMiddleware;
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

    public function asText(): TextResponse
    {
        if ($this->hasTools()) {
            return $this->executeWithTools();
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'text');

        return $driver->text($this->buildRequest());
    }

    public function asStream(): StreamResponse
    {
        if ($this->hasTools()) {
            throw new AtlasException('Streaming with tools is not yet supported. Use asText() for tool-enabled requests.');
        }

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'stream');

        return $driver->stream($this->buildRequest());
    }

    public function asStructured(): StructuredResponse
    {
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
}
