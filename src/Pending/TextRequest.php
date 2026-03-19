<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Concerns\NormalizesMessages;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Requests\TextRequest as TextRequestObject;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;

/**
 * Fluent builder for text generation, streaming, and structured output requests.
 */
class TextRequest
{
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

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly string $model,
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

    public function asText(): TextResponse
    {
        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'text');

        return $driver->text($this->buildRequest());
    }

    public function asStream(): StreamResponse
    {
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

    public function buildRequest(): TextRequestObject
    {
        return new TextRequestObject(
            model: $this->model,
            instructions: $this->instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->messages,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: [],           // Wired via AgentRequest in Phase 7
            providerTools: [],   // Wired via AgentRequest in Phase 7
            providerOptions: $this->providerOptions,
        );
    }
}
