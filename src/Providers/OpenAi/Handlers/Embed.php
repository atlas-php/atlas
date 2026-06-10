<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * OpenAI embeddings handler using the /v1/embeddings endpoint.
 */
class Embed implements EmbedHandler
{
    use BuildsHeaders, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function embed(EmbedRequest $request): EmbeddingsResponse
    {
        $body = [
            'model' => $request->model,
            'input' => $request->input,
        ];

        // text-embedding-3-* models accept a `dimensions` param (Matryoshka
        // truncation). Forward the configured embedding dimensions so the value
        // that sizes the storage column also shapes the returned vector — they
        // can no longer silently disagree. Only fill it when the model supports
        // the param and the caller has not already set it; ada-002 and other
        // models reject `dimensions`, so they are left untouched.
        if (str_starts_with($request->model, 'text-embedding-3')
            && ! array_key_exists('dimensions', $request->providerOptions)) {
            $dimensions = config('atlas.embeddings.dimensions');
            if ($dimensions !== null) {
                $body['dimensions'] = (int) $dimensions;
            }
        }

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/embeddings",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
        );

        /** @var array<int, array<string, mixed>> $items */
        $items = $data['data'] ?? [];

        // Order by the response `index` rather than trusting array position.
        // OpenAI documents an `index` per embedding precisely so batches can be
        // realigned; the chunked pipeline assigns vectors to chunks positionally
        // ($vectors[$i] → $insert[$i]), so an out-of-order batch would silently
        // attach the wrong vector to every chunk. No-op when already ordered.
        usort(
            $items,
            fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0),
        );

        $embeddings = array_map(
            fn (array $item): array => $item['embedding'] ?? [],
            $items,
        );

        /** @var array<string, mixed> $usage */
        $usage = $data['usage'] ?? [];

        return new EmbeddingsResponse(
            embeddings: $embeddings,
            usage: new Usage(
                inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
                outputTokens: 0,
            ),
        );
    }
}
