<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * OpenAI image handler using the DALL-E image generation endpoint.
 */
class Image implements ImageHandler
{
    use BuildsHeaders, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function image(ImageRequest $request): ImageResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'prompt' => $request->instructions,
            'size' => $request->size,
            'quality' => $request->quality,
            'n' => $request->count,
        ], fn (mixed $v): bool => $v !== null);

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/images/generations",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        /** @var array<int, array<string, mixed>> $results */
        $results = $data['data'] ?? [];

        if ($request->count === 1) {
            $first = $results[0] ?? [];

            return new ImageResponse(
                url: (string) ($first['url'] ?? $first['b64_json'] ?? ''),
                revisedPrompt: isset($first['revised_prompt']) ? (string) $first['revised_prompt'] : null,
                meta: ['model' => $request->model],
            );
        }

        $urls = array_map(
            fn (array $item): string => (string) ($item['url'] ?? $item['b64_json'] ?? ''),
            $results,
        );

        $revisedPrompt = isset($results[0]['revised_prompt'])
            ? (string) $results[0]['revised_prompt']
            : null;

        return new ImageResponse(
            url: $urls,
            revisedPrompt: $revisedPrompt,
            meta: ['model' => $request->model, 'count' => count($results)],
        );
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('imageToText', 'openai');
    }
}
