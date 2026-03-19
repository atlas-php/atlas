<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * OpenAI image handler using the DALL-E image generation endpoint.
 */
class Image implements ImageHandler
{
    use BuildsHeaders;

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
            'n' => 1,
        ], fn (mixed $v): bool => $v !== null);

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/images/generations",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        /** @var array<string, mixed> $first */
        $first = $data['data'][0] ?? [];

        return new ImageResponse(
            url: (string) ($first['url'] ?? $first['b64_json'] ?? ''),
            revisedPrompt: isset($first['revised_prompt']) ? (string) $first['revised_prompt'] : null,
            meta: ['model' => $request->model],
        );
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('imageToText', 'openai');
    }
}
