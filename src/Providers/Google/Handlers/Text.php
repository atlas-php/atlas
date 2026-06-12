<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Providers\Concerns\AppliesToolChoice;
use Atlasphp\Atlas\Providers\Google\Concerns\BuildsGoogleHeaders;
use Atlasphp\Atlas\Providers\Google\MediaResolver;
use Atlasphp\Atlas\Providers\Google\MessageFactory;
use Atlasphp\Atlas\Providers\Google\ResponseParser;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\SseParser;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\TokenCount;
use Generator;

/**
 * Gemini text handler using the generateContent endpoint.
 *
 * Handles text generation, streaming with data-only SSE,
 * and structured output via responseMimeType and responseSchema.
 */
class Text implements TextHandler
{
    use AppliesToolChoice;
    use BuildsGoogleHeaders;

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
            url: $this->endpoint($request->model, 'generateContent'),
            headers: $this->headers(),
            body: $this->buildBody($request),
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        return $this->parser->parseText($data);
    }

    /**
     * Parse a generateContent payload into a TextResponse.
     *
     * Public so the batch handler parses a batch line's result identically.
     *
     * @param  array<string, mixed>  $data
     */
    public function parse(array $data): TextResponse
    {
        return $this->parser->parseText($data);
    }

    public function stream(TextRequest $request): StreamResponse
    {
        $raw = $this->http->stream(
            url: $this->endpoint($request->model, 'streamGenerateContent').'?alt=sse',
            headers: $this->headers(),
            body: $this->buildBody($request),
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        return new StreamResponse($this->parseSSE($raw, $request->model));
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        $body = $this->buildBody($request);

        $body['generationConfig'] = array_merge($body['generationConfig'] ?? [], [
            'responseMimeType' => 'application/json',
            'responseSchema' => $request->schema?->toArray() ?? [],
        ]);

        $data = $this->http->post(
            url: $this->endpoint($request->model, 'generateContent'),
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        $text = $this->parser->parseText($data);

        return new StructuredResponse(
            structured: json_decode($text->text, true) ?? [],
            usage: $text->usage,
            finishReason: $text->finishReason,
            meta: $text->meta,
        );
    }

    public function countTokens(TextRequest $request): TokenCount
    {
        // The countTokens endpoint only counts system_instruction and tools when
        // the full request is wrapped in generateContentRequest; the bare
        // `contents` form silently ignores them.
        $body = [
            'generateContentRequest' => array_merge(
                ['model' => "models/{$request->model}"],
                $this->buildBody($request),
            ),
        ];

        $data = $this->http->post(
            url: $this->endpoint($request->model, 'countTokens'),
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        $breakdown = [];

        if (isset($data['cachedContentTokenCount'])) {
            $breakdown['cached_tokens'] = (int) $data['cachedContentTokenCount'];
        }

        return new TokenCount(
            inputTokens: (int) ($data['totalTokens'] ?? 0),
            estimated: false,
            provider: $this->config->provider,
            model: $request->model,
            breakdown: $breakdown,
        );
    }

    /**
     * Build the Gemini request body.
     *
     * Public so the batch handler serializes a batch line identically to a
     * synchronous call.
     *
     * @return array<string, mixed>
     */
    public function buildBody(TextRequest $request): array
    {
        $messageData = $this->messages->buildAll($request, $this->media);

        $body = [
            'contents' => $messageData['contents'],
        ];

        if ($messageData['system_instruction'] !== null) {
            $body['system_instruction'] = $messageData['system_instruction'];
        }

        $genConfig = array_filter([
            'maxOutputTokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ]);

        if ($genConfig !== []) {
            $body['generationConfig'] = $genConfig;
        }

        if ($request->reasoning !== null) {
            $body['generationConfig'] ??= [];
            $body['generationConfig']['thinkingConfig'] = [
                'thinkingBudget' => $request->reasoning->budgetTokens(),
                'includeThoughts' => $request->reasoning->includeSummary,
            ];
        }

        if ($request->tools !== []) {
            $body['tools'][] = [
                'function_declarations' => $this->tools->mapTools($request->tools),
            ];
            $body = $this->applyToolChoice($body, $request, $this->tools);
        }

        if ($request->providerTools !== []) {
            foreach ($this->tools->mapProviderTools($request->providerTools) as $providerTool) {
                $body['tools'][] = $providerTool;
            }
        }

        // array_replace_recursive (not array_merge_recursive): deep-merges nested
        // generationConfig so escape-hatch keys (topP, topK, …) sit alongside the
        // fields Atlas sets, while a colliding scalar (e.g. temperature) is
        // overridden rather than turned into a list — array_merge_recursive would
        // produce ["temperature" => [0.7, 0.2]] and Gemini 400s on the array.
        return array_replace_recursive($body, $request->providerOptions);
    }

    /**
     * Parse Gemini SSE stream (data-only lines, no named events).
     *
     * Gemini's final chunk often carries both text content AND finishReason/usage.
     * The ResponseParser returns this as a Done chunk with text embedded.
     * We split it into a Text chunk (for the content) followed by a Done chunk
     * (for usage/finishReason) so consumers iterating by ChunkType see the text.
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse, string $model = ''): Generator
    {
        foreach (SseParser::parseDataOnly($rawResponse) as $data) {
            $chunk = $this->parser->parseStreamChunk($data, $model);

            // Split Done chunks that carry text into Text + Done
            if ($chunk->type === ChunkType::Done && $chunk->text !== null) {
                yield new StreamChunk(type: ChunkType::Text, text: $chunk->text);
                yield new StreamChunk(
                    type: ChunkType::Done,
                    usage: $chunk->usage,
                    finishReason: $chunk->finishReason,
                );

                continue;
            }

            yield $chunk;
        }
    }

    protected function endpoint(string $model, string $method): string
    {
        return "{$this->config->baseUrl}/v1beta/models/{$model}:{$method}";
    }
}
