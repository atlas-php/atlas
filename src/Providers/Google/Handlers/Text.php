<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Providers\Google\BuildsGoogleHeaders;
use Atlasphp\Atlas\Providers\Google\MediaResolver;
use Atlasphp\Atlas\Providers\Google\MessageFactory;
use Atlasphp\Atlas\Providers\Google\ResponseParser;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Generator;

/**
 * Gemini text handler using the generateContent endpoint.
 *
 * Handles text generation, streaming with data-only SSE,
 * and structured output via responseMimeType and responseSchema.
 */
class Text implements TextHandler
{
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
        );

        return $this->parser->parseText($data);
    }

    public function stream(TextRequest $request): StreamResponse
    {
        $raw = $this->http->stream(
            url: $this->endpoint($request->model, 'streamGenerateContent').'?alt=sse',
            headers: $this->headers(),
            body: $this->buildBody($request),
            timeout: $this->config->timeout,
        );

        return new StreamResponse($this->parseSSE($raw));
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
        );

        $text = $this->parser->parseText($data);

        return new StructuredResponse(
            structured: json_decode($text->text, true) ?? [],
            usage: $text->usage,
            finishReason: $text->finishReason,
            meta: $text->meta,
        );
    }

    /**
     * Build the Gemini request body.
     *
     * @return array<string, mixed>
     */
    protected function buildBody(TextRequest $request): array
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

        if ($request->tools !== []) {
            $body['tools'][] = [
                'function_declarations' => $this->tools->mapTools($request->tools),
            ];
        }

        if ($request->providerTools !== []) {
            foreach ($this->tools->mapProviderTools($request->providerTools) as $providerTool) {
                $body['tools'][] = $providerTool;
            }
        }

        return array_merge_recursive($body, $request->providerOptions);
    }

    /**
     * Parse Gemini SSE stream (data-only lines, no named events).
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse): Generator
    {
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

    protected function endpoint(string $model, string $method): string
    {
        return "{$this->config->baseUrl}/v1beta/models/{$model}:{$method}";
    }
}
