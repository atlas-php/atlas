<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

use Atlasphp\Atlas\Schema\Schema;

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
        public readonly array $middleware = [],
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
            middleware: $this->middleware,
        );
    }
}
