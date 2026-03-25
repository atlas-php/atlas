<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Google\Concerns\BuildsGoogleHeaders;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Gemini image handler using generateContent with response modalities.
 */
class Image implements ImageHandler
{
    use BuildsGoogleHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function image(ImageRequest $request): ImageResponse
    {
        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $request->instructions ?? '']],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE', 'TEXT'],
            ],
        ];

        $body = array_merge_recursive($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v1beta/models/{$request->model}:generateContent",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $imageData = null;
        $mimeType = 'image/png';
        $revisedPrompt = null;

        foreach ($parts as $part) {
            // Gemini returns camelCase keys (inlineData) in responses
            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if ($inlineData !== null) {
                $imageData = $inlineData['data'] ?? null;
                $mimeType = $inlineData['mimeType'] ?? $inlineData['mime_type'] ?? 'image/png';
            }
            if (isset($part['text'])) {
                $revisedPrompt = $part['text'];
            }
        }

        return new ImageResponse(
            url: $imageData !== null ? "data:{$mimeType};base64,{$imageData}" : '',
            revisedPrompt: $revisedPrompt,
            base64: $imageData,
            meta: ['model' => $request->model],
        );
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('imageToText', 'google');
    }
}
