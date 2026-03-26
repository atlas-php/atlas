<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic\Handlers;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;
use Atlasphp\Atlas\Providers\Anthropic\MessageFactory;
use Atlasphp\Atlas\Providers\Anthropic\ResponseParser;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\SseParser;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Generator;

/**
 * Anthropic text handler using the Messages API.
 *
 * Handles text generation, streaming with named SSE events,
 * and structured output via tool_choice forced tool or JSON schema.
 */
class Text implements TextHandler
{
    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly MessageFactory $messages,
        protected readonly MediaResolver $media,
        protected readonly ToolMapper $tools,
        protected readonly ResponseParser $parser,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/messages",
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

        $raw = $this->http->stream(
            url: "{$this->config->baseUrl}/messages",
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
            $body['tools'] = array_merge($body['tools'] ?? [], [
                [
                    'name' => $request->schema->name(),
                    'description' => $request->schema->description(),
                    'input_schema' => $request->schema->toArray(),
                ],
            ]);
            $body['tool_choice'] = ['type' => 'tool', 'name' => $request->schema->name()];
        }

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/messages",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        $textResponse = $this->parser->parseText($data);

        $structured = $textResponse->toolCalls !== []
            ? $textResponse->toolCalls[0]->arguments
            : [];

        return new StructuredResponse(
            structured: $structured,
            usage: $textResponse->usage,
            finishReason: $textResponse->finishReason,
            meta: $textResponse->meta,
        );
    }

    /**
     * Build the Anthropic Messages API request body.
     *
     * @return array<string, mixed>
     */
    protected function buildBody(TextRequest $request): array
    {
        $messageData = $this->messages->buildAll($request, $this->media);

        $body = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens ?? 4096,
            'messages' => $messageData['messages'],
        ];

        if ($messageData['system'] !== null) {
            $body['system'] = $messageData['system'];
        }

        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }

        if ($request->tools !== []) {
            $body['tools'] = $this->tools->mapTools($request->tools);
        }

        return array_merge($body, $request->providerOptions);
    }

    /**
     * Parse Anthropic SSE stream with named events.
     *
     * Tracks tool_use content blocks across content_block_start, input_json_delta,
     * and content_block_stop events to emit complete ToolCall chunks.
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse): Generator
    {
        /** @var array<int, array{id: string, name: string, json: string}> $toolBlocks */
        $toolBlocks = [];

        // Input tokens arrive in message_start, output tokens in message_delta.
        // Stash input count here so the Done chunk has the full picture.
        $stashedInputTokens = 0;

        foreach (SseParser::parse($rawResponse) as ['event' => $event, 'data' => $data]) {
            // Capture input tokens from message_start (not in message_delta)
            if ($event === 'message_start') {
                $stashedInputTokens = (int) ($data['message']['usage']['input_tokens'] ?? 0);

                continue;
            }

            // Track tool_use block starts
            if ($event === 'content_block_start') {
                $block = $data['content_block'] ?? [];

                if (($block['type'] ?? '') === 'tool_use') {
                    $index = $data['index'] ?? 0;
                    $toolBlocks[$index] = [
                        'id' => $block['id'] ?? '',
                        'name' => $block['name'] ?? '',
                        'json' => '',
                    ];
                }

                continue;
            }

            // Accumulate tool call JSON
            if ($event === 'content_block_delta') {
                $delta = $data['delta'] ?? [];

                if (($delta['type'] ?? '') === 'input_json_delta') {
                    $index = $data['index'] ?? 0;

                    if (isset($toolBlocks[$index])) {
                        $toolBlocks[$index]['json'] .= $delta['partial_json'] ?? '';
                    }

                    continue;
                }
            }

            // Emit Done chunk with full usage on message_delta
            if ($event === 'message_delta') {
                $delta = $data['delta'] ?? [];
                $usage = $data['usage'] ?? [];

                yield new StreamChunk(
                    type: ChunkType::Done,
                    usage: $usage !== [] ? new Usage(
                        inputTokens: $stashedInputTokens,
                        outputTokens: (int) ($usage['output_tokens'] ?? 0),
                    ) : null,
                    finishReason: isset($delta['stop_reason'])
                        ? $this->parser->parseFinishReason(['stop_reason' => $delta['stop_reason']])
                        : null,
                );

                continue;
            }

            // Emit completed tool call on block stop
            if ($event === 'content_block_stop') {
                $index = $data['index'] ?? 0;

                if (isset($toolBlocks[$index])) {
                    $block = $toolBlocks[$index];
                    $arguments = json_decode($block['json'], true) ?? [];

                    yield new StreamChunk(
                        type: ChunkType::ToolCall,
                        toolCalls: [new ToolCall($block['id'], $block['name'], $arguments)],
                    );

                    unset($toolBlocks[$index]);

                    continue;
                }
            }

            yield $this->parser->parseStreamChunk([
                'event' => $event,
                'data' => $data,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'x-api-key' => $this->config->apiKey,
            'anthropic-version' => $this->config->extra['version'] ?? '2023-06-01',
            'Content-Type' => 'application/json',
        ];
    }
}
