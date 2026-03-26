<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions\Handlers;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\ChatCompletions\MediaResolver;
use Atlasphp\Atlas\Providers\ChatCompletions\MessageFactory;
use Atlasphp\Atlas\Providers\ChatCompletions\ResponseParser;
use Atlasphp\Atlas\Providers\ChatCompletions\ToolMapper;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\SseParser;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Generator;

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
            meta: $textResponse->meta,
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
     * Accumulates tool call argument fragments across deltas (indexed by position)
     * and emits complete ToolCall chunks when finish_reason indicates completion.
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse): Generator
    {
        /** @var array<int, array{id: string, name: string, arguments: string}> $toolBlocks */
        $toolBlocks = [];

        foreach (SseParser::parseDataOnly($rawResponse) as $data) {
            $delta = $data['choices'][0]['delta'] ?? [];
            $finishReason = $data['choices'][0]['finish_reason'] ?? null;

            // Accumulate tool call deltas by index
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $tc) {
                    $index = $tc['index'] ?? 0;

                    if (isset($tc['id'])) {
                        // First delta for this tool call — initialize
                        $toolBlocks[$index] = [
                            'id' => $tc['id'],
                            'name' => $tc['function']['name'] ?? '',
                            'arguments' => $tc['function']['arguments'] ?? '',
                        ];
                    } elseif (isset($toolBlocks[$index])) {
                        // Subsequent delta — append argument fragment
                        $toolBlocks[$index]['arguments'] .= $tc['function']['arguments'] ?? '';
                    }
                }

                // If this delta also carries finish_reason, fall through to emit
                if ($finishReason === null) {
                    continue;
                }
            }

            // On finish_reason, emit accumulated tool calls
            if ($finishReason !== null && $toolBlocks !== []) {
                $toolCalls = array_map(fn (array $block) => new ToolCall(
                    $block['id'],
                    $block['name'],
                    json_decode($block['arguments'], true) ?? [],
                ), $toolBlocks);

                yield new StreamChunk(
                    type: ChunkType::ToolCall,
                    toolCalls: array_values($toolCalls),
                );

                $toolBlocks = [];
            }

            yield $this->parser->parseStreamChunk($data);
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
