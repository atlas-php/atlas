<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Providers\Contracts\MessageFactory as MessageFactoryContract;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * OpenAI text handler using the Responses API.
 *
 * Handles text generation, streaming with named SSE events,
 * and structured output via json_schema format.
 */
class Text implements TextHandler
{
    use BuildsHeaders;

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
        );

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
        );

        return new StreamResponse($this->parseSSE($raw));
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        $body = $this->buildPayload($request);

        if ($request->schema !== null) {
            $body['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $request->schema->name(),
                    'schema' => $request->schema->toArray(),
                    'strict' => true,
                ],
            ];
        }

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/responses",
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
     * Build the Responses API request payload.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(TextRequest $request): array
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
        }

        return array_merge($body, $request->providerOptions);
    }

    /**
     * Parse Responses API SSE stream with named events.
     *
     * @return Generator<int, StreamChunk>
     */
    protected function parseSSE(mixed $rawResponse): Generator
    {
        /** @var StreamInterface $body */
        $body = $rawResponse->getBody();
        $buffer = '';
        $currentEvent = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $raw = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $raw) as $line) {
                    if (str_starts_with($line, 'event: ')) {
                        $currentEvent = substr($line, 7);
                    } elseif (str_starts_with($line, 'data: ')) {
                        $json = substr($line, 6);

                        if ($json === '[DONE]') {
                            return;
                        }

                        $data = json_decode($json, true);

                        if ($data !== null) {
                            yield $this->parser->parseStreamChunk([
                                'event' => $currentEvent,
                                'data' => $data,
                            ]);
                        }
                    }
                }

                $currentEvent = '';
            }
        }
    }
}
