<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\HasOrganizationHeader;
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

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/embeddings",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $items */
        $items = $data['data'] ?? [];

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
