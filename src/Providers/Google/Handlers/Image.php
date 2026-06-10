<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Google\Concerns\BuildsGoogleHeaders;
use Atlasphp\Atlas\Providers\Google\MediaResolver;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Gemini image handler using generateContent with response modalities.
 *
 * Supports text-to-image and image-to-image: any reference media on the request
 * is resolved into inline_data parts alongside the prompt, so Gemini conditions
 * the generation on the supplied image(s) — the basis for identity-preserving
 * edits and reference-anchored generation.
 */
class Image implements ImageHandler
{
    use BuildsGoogleHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly MediaResolver $media,
    ) {}

    public function image(ImageRequest $request): ImageResponse
    {
        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $request->instructions ?? ''], ...$this->mediaParts($request)],
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
            config: $request->requestConfig,
            context: new ProviderRequestContext($this->config->provider, $request->model),
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

    /**
     * Resolve the request's reference media into Gemini inline_data/file_data
     * parts, appended after the prompt text so the generation is conditioned on
     * them (image-to-image). Empty when no media was supplied.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mediaParts(ImageRequest $request): array
    {
        return array_values(array_map(
            fn (Input $input): array => $this->media->resolve($input),
            array_filter($request->media, static fn (mixed $m): bool => $m instanceof Input),
        ));
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('imageToText', 'google');
    }
}
