<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Extracts normalized data from provider-specific response JSON.
 */
interface ResponseParserContract
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function parseText(array $data): TextResponse;

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseUsage(array $data): Usage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseFinishReason(array $data): FinishReason;

    /**
     * Parse a streaming event into a StreamChunk.
     *
     * The provider name is threaded through (alongside $model) so a mid-stream
     * error is attributed to the configured provider — parsers shared across
     * providers (OpenAI/xAI, the chat_completions drivers) must not hardcode it.
     *
     * @param  array<string, mixed>  $data
     */
    public function parseStreamChunk(array $data, string $model = '', string $provider = ''): StreamChunk;
}
