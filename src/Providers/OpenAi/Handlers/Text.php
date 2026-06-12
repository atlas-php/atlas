<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Providers\Concerns\AppliesToolChoice;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Concerns\CountsTokens;
use Atlasphp\Atlas\Providers\Contracts\MessageFactoryContract;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\SseParser;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\TokenCount;
use Atlasphp\Atlas\Schema\StrictSchema;
use Generator;
use Illuminate\Http\Client\RequestException;

/**
 * OpenAI text handler using the Responses API.
 *
 * Handles text generation, streaming with named SSE events,
 * and structured output via json_schema format.
 */
class Text implements TextHandler
{
    use AppliesToolChoice;
    use BuildsHeaders, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }
    use CountsTokens;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly MessageFactoryContract $messages,
        protected readonly MediaResolver $media,
        protected readonly ToolMapper $toolMapper,
        protected readonly ResponseParser $parser,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/responses",
            headers: $this->headers(),
            body: $this->buildPayload($request),
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        return $this->parser->parseText($data);
    }

    /**
     * Parse a Responses API payload into a TextResponse.
     *
     * Public so the batch handler parses a batch line's result identically to a
     * synchronous call.
     *
     * @param  array<string, mixed>  $data
     */
    public function parse(array $data): TextResponse
    {
        return $this->parser->parseText($data);
    }

    public function stream(TextRequest $request): StreamResponse
    {
        $body = $this->buildPayload($request);
        $body['stream'] = true;

        $raw = $this->http->stream(
            url: "{$this->config->baseUrl}/responses",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        return new StreamResponse($this->parseSSE($raw, $request->model, $this->config->providerName('openai')));
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        $body = $this->buildPayload($request);

        if ($request->schema !== null) {
            $body['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $request->schema->name(),
                    'schema' => StrictSchema::normalize($request->schema->toArray()),
                    'strict' => true,
                ],
            ];
        }

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/responses",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        $textResponse = $this->parser->parseText($data);

        return new StructuredResponse(
            structured: json_decode($textResponse->text, true) ?? [],
            usage: $textResponse->usage,
            finishReason: $textResponse->finishReason,
            meta: $textResponse->meta,
        );
    }

    public function countTokens(TextRequest $request): TokenCount
    {
        $payload = $this->buildPayload($request);

        // The input_tokens endpoint shares the Responses shape but rejects the
        // generation controls; keep only what contributes to the input (model,
        // input, instructions, tools).
        unset(
            $payload['max_output_tokens'],
            $payload['temperature'],
            $payload['stream'],
            $payload['store'],
            $payload['text'],
            $payload['reasoning'],
            $payload['include'],
        );

        try {
            $data = $this->http->post(
                url: "{$this->config->baseUrl}/responses/input_tokens",
                headers: $this->headers(),
                body: $payload,
                timeout: $this->config->timeout,
                config: $request->requestConfig,
                context: new ProviderRequestContext($this->config->provider, $request->model),
            );
        } catch (RequestException $e) {
            // OpenAI-compatible endpoints (Ollama, LM Studio) reuse this handler
            // but may not implement input_tokens — fall back to a heuristic.
            if (in_array($e->response->status(), [400, 404, 405], true)) {
                return $this->estimateTokens($this->config->provider, $request->model, $payload);
            }

            throw $e;
        }

        return new TokenCount(
            inputTokens: (int) ($data['input_tokens'] ?? 0),
            estimated: false,
            provider: $this->config->provider,
            model: $request->model,
        );
    }

    /**
     * Build the Responses API request payload.
     *
     * Public so the batch handler can serialize a batch line's body identically
     * to a synchronous call — the two paths must never produce divergent bodies.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(TextRequest $request): array
    {
        $messageData = $this->messages->buildAll($request, $this->media);

        $body = [
            'model' => $request->model,
            'input' => $messageData['input'],
            'store' => false,
        ];

        if ($messageData['instructions'] !== null) {
            $body['instructions'] = $messageData['instructions'];
        }

        if ($request->maxTokens !== null) {
            $body['max_output_tokens'] = $request->maxTokens;
        }

        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }

        $tools = [];

        if ($request->tools !== []) {
            $tools = array_merge($tools, $this->toolMapper->mapTools($request->tools));
        }

        if ($request->providerTools !== []) {
            $tools = array_merge($tools, $this->toolMapper->mapProviderTools($request->providerTools));
        }

        if ($tools !== []) {
            $body['tools'] = $tools;
            $body = $this->applyToolChoice($body, $request, $this->toolMapper);
        }

        if ($request->reasoning !== null) {
            $reasoning = ['effort' => $request->reasoning->effort->value];

            if ($request->reasoning->includeSummary) {
                $reasoning['summary'] = 'auto';
            }

            $body['reasoning'] = $reasoning;

            // store=false means there is no server-side state to carry reasoning
            // across tool turns; ask for the encrypted reasoning so Atlas can
            // replay the reasoning items itself on the next request.
            $body['include'] = array_values(array_unique(
                array_merge($body['include'] ?? [], ['reasoning.encrypted_content']),
            ));
        }

        return array_merge($body, $request->providerOptions);
    }

    /**
     * Parse Responses API SSE stream with named events.
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse, string $model = '', string $provider = 'openai'): Generator
    {
        foreach (SseParser::parse($rawResponse) as $event) {
            yield $this->parser->parseStreamChunk($event, $model, $provider);
        }
    }
}
