<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Google\Concerns\BuildsGoogleHeaders;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Gemini embeddings handler using embedContent and batchEmbedContents endpoints.
 */
class Embed implements EmbedHandler
{
    use BuildsGoogleHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function embed(EmbedRequest $request): EmbeddingsResponse
    {
        $inputs = is_array($request->input) ? $request->input : [$request->input];

        if (count($inputs) > 1) {
            return $this->batchEmbed($request, $inputs);
        }

        $body = [
            'model' => "models/{$request->model}",
            'content' => [
                'parts' => [['text' => $inputs[0]]],
            ],
        ];

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v1beta/models/{$request->model}:embedContent",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        // Gemini returns totalTokenCount with no input/output split — embeddings have no output tokens
        $totalTokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);

        return new EmbeddingsResponse(
            embeddings: [$data['embedding']['values'] ?? []],
            usage: new Usage(inputTokens: $totalTokens, outputTokens: 0),
        );
    }

    /**
     * @param  array<int, string>  $inputs
     */
    protected function batchEmbed(EmbedRequest $request, array $inputs): EmbeddingsResponse
    {
        $requests = array_map(fn (string $text): array => [
            'model' => "models/{$request->model}",
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ], $inputs);

        if ($request->providerOptions !== []) {
            $requests = array_map(
                fn (array $r): array => array_merge($r, $request->providerOptions),
                $requests,
            );
        }

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v1beta/models/{$request->model}:batchEmbedContents",
            headers: $this->headers(),
            body: ['requests' => $requests],
            timeout: $this->config->timeout,
        );

        $embeddings = array_map(
            fn (array $e): array => $e['values'] ?? [],
            $data['embeddings'] ?? [],
        );

        $totalTokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);

        return new EmbeddingsResponse(
            embeddings: $embeddings,
            usage: new Usage(inputTokens: $totalTokens, outputTokens: 0),
        );
    }
}
