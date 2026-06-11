<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

use Atlasphp\Atlas\RequestConfig;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolChoice;

/**
 * Request object for text generation, streaming, and structured output.
 */
final class TextRequest
{
    /**
     * @param  array<int, mixed>  $messageMedia
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     * @param  array<int, mixed>  $providerTools
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, mixed>  $middleware
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $instructions,
        public readonly ?string $message,
        public readonly array $messageMedia,
        public readonly array $messages,
        public readonly ?int $maxTokens,
        public readonly ?float $temperature,
        public readonly ?Schema $schema,
        public readonly array $tools,
        public readonly array $providerTools,
        public readonly array $providerOptions,
        public readonly ?ToolChoice $toolChoice = null,
        public readonly array $middleware = [],
        public readonly array $meta = [],
        public readonly bool $cache = false,
        public readonly ?RequestConfig $requestConfig = null,
        public readonly ?Reasoning $reasoning = null,
    ) {}

    /**
     * Return a new instance with additional messages appended.
     *
     * @param  array<int, mixed>  $messages
     */
    public function withAppendedMessages(array $messages): self
    {
        return new self(
            model: $this->model,
            instructions: $this->instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: array_merge($this->messages, $messages),
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: $this->tools,
            providerTools: $this->providerTools,
            providerOptions: $this->providerOptions,
            toolChoice: $this->toolChoice,
            middleware: $this->middleware,
            meta: $this->meta,
            cache: $this->cache,
            requestConfig: $this->requestConfig,
            reasoning: $this->reasoning,
        );
    }

    /**
     * Return a copy with the tools array replaced entirely.
     *
     * @param  array<int, mixed>  $tools
     */
    public function withReplacedTools(array $tools): self
    {
        return new self(
            model: $this->model,
            instructions: $this->instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->messages,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: $tools,
            providerTools: $this->providerTools,
            providerOptions: $this->providerOptions,
            toolChoice: $this->toolChoice,
            middleware: $this->middleware,
            meta: $this->meta,
            cache: $this->cache,
            requestConfig: $this->requestConfig,
            reasoning: $this->reasoning,
        );
    }

    /**
     * Return a copy with the message and messageMedia cleared.
     *
     * Used by the executor to move the user message into the messages
     * array so it appears in the correct position in conversation history.
     */
    public function withClearedMessage(): self
    {
        return new self(
            model: $this->model,
            instructions: $this->instructions,
            message: null,
            messageMedia: [],
            messages: $this->messages,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: $this->tools,
            providerTools: $this->providerTools,
            providerOptions: $this->providerOptions,
            toolChoice: $this->toolChoice,
            middleware: $this->middleware,
            meta: $this->meta,
            cache: $this->cache,
            requestConfig: $this->requestConfig,
            reasoning: $this->reasoning,
        );
    }

    /**
     * Return a copy with the messages array replaced entirely.
     *
     * @param  array<int, mixed>  $messages
     */
    public function withReplacedMessages(array $messages): self
    {
        return new self(
            model: $this->model,
            instructions: $this->instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $messages,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: $this->tools,
            providerTools: $this->providerTools,
            providerOptions: $this->providerOptions,
            toolChoice: $this->toolChoice,
            middleware: $this->middleware,
            meta: $this->meta,
            cache: $this->cache,
            requestConfig: $this->requestConfig,
            reasoning: $this->reasoning,
        );
    }

    /**
     * Return a copy with the tool choice replaced.
     *
     * Used by the executor to relax a forced choice after the opening step so the
     * model can still produce a final text reply.
     */
    public function withToolChoice(?ToolChoice $toolChoice): self
    {
        return new self(
            model: $this->model,
            instructions: $this->instructions,
            message: $this->message,
            messageMedia: $this->messageMedia,
            messages: $this->messages,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            schema: $this->schema,
            tools: $this->tools,
            providerTools: $this->providerTools,
            providerOptions: $this->providerOptions,
            toolChoice: $toolChoice,
            middleware: $this->middleware,
            meta: $this->meta,
            cache: $this->cache,
            requestConfig: $this->requestConfig,
            reasoning: $this->reasoning,
        );
    }
}
