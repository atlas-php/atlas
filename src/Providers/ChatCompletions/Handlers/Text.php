<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions\Handlers;

use Atlasphp\Atlas\Providers\ChatCompletions\MediaResolver;
use Atlasphp\Atlas\Providers\ChatCompletions\MessageFactory;
use Atlasphp\Atlas\Providers\ChatCompletions\ResponseParser;
use Atlasphp\Atlas\Providers\ChatCompletions\ToolMapper;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Chat Completions text handler for /v1/chat/completions.
 *
 * Handles text generation, data-only SSE streaming, and structured
 * output via response_format.
 */
class Text implements TextHandler
{
    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly MessageFactory $messages,
        protected readonly MediaResolver $media,
        protected readonly ToolMapper $toolMapper,
        protected readonly ResponseParser $parser,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/chat/completions",
            headers: $this->headers(),
            body: $this->buildBody($request),
            timeout: $this->config->timeout,
        );

        return $this->parser->parseText($data);
    }

    public function stream(TextRequest $request): StreamResponse
    {
        $body = $this->buildBody($request);
        $body['stream'] = true;
        $body['stream_options'] = ['include_usage' => true];

        $raw = $this->http->stream(
            url: "{$this->config->baseUrl}/chat/completions",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        return new StreamResponse($this->parseSSE($raw));
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        $body = $this->buildBody($request);

        if ($request->schema !== null) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->schema->name(),
                    'strict' => true,
                    'schema' => $request->schema->toArray(),
                ],
            ];
        }

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/chat/completions",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        $textResponse = $this->parser->parseText($data);

        return new StructuredResponse(
            structured: json_decode($textResponse->text, true) ?? [],
            usage: $textResponse->usage,
            finishReason: $textResponse->finishReason,
        );
    }

    /**
     * Build the Chat Completions request body.
     *
     * @return array<string, mixed>
     */
    protected function buildBody(TextRequest $request): array
    {
        $messageData = $this->messages->buildAll($request, $this->media);

        $body = array_filter([
            'model' => $request->model,
            'messages' => $messageData['messages'],
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ], fn ($v) => $v !== null);

        if ($request->tools !== []) {
            $body['tools'] = $this->toolMapper->mapTools($request->tools);
        }

        return array_merge($body, $request->providerOptions);
    }

    /**
     * Parse Chat Completions data-only SSE stream.
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse): Generator
    {
        /** @var StreamInterface $body */
        $body = $rawResponse->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $json = json_decode(substr($line, 6), true);

                    if ($json !== null) {
                        yield $this->parser->parseStreamChunk($json);
                    }
                }
            }
        }
    }

    /**
     * Build request headers with optional Bearer auth.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($this->config->apiKey !== '') {
            $headers['Authorization'] = "Bearer {$this->config->apiKey}";
        }

        return $headers;
    }
}
