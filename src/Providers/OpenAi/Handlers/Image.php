<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * OpenAI image handler for the images/generations endpoint.
 *
 * Handles both response shapes: hosted URLs (legacy DALL-E) and base64
 * payloads (b64_json, the only mode for gpt-image-* models).
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
        // Reference media → image-to-image edit (multipart /images/edits);
        // otherwise plain text-to-image generation.
        $data = $request->media !== []
            ? $this->edit($request)
            : $this->generate($request);

        return $this->parse($data, $request);
    }

    /**
     * Text-to-image generation via /images/generations.
     *
     * @return array<string, mixed>
     */
    private function generate(ImageRequest $request): array
    {
        $body = array_filter([
            'model' => $request->model,
            'prompt' => $request->instructions,
            'size' => $request->size,
            'quality' => $request->quality,
            'n' => $request->count,
        ], fn (mixed $v): bool => $v !== null);

        $body = array_merge($body, $request->providerOptions);

        return $this->http->post(
            url: "{$this->config->baseUrl}/images/generations",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );
    }

    /**
     * Image-to-image via /images/edits — the reference media is uploaded as
     * `image[]` so the model conditions the generation on it (identity-preserving
     * edits / reference-anchored generation). Multipart, so Content-Type is set
     * by the transport, not the JSON header.
     *
     * @return array<string, mixed>
     */
    private function edit(ImageRequest $request): array
    {
        $fields = array_filter([
            'model' => $request->model,
            'prompt' => $request->instructions,
            'size' => $request->size,
            'quality' => $request->quality,
            'n' => (string) $request->count,
        ], fn (mixed $v): bool => $v !== null);

        foreach ($request->providerOptions as $key => $value) {
            if (is_scalar($value)) {
                $fields[$key] = (string) $value;
            }
        }

        $attachments = [];
        $index = 0;

        foreach ($request->media as $input) {
            if (! $input instanceof Input) {
                continue;
            }

            $attachments[] = [
                'name' => 'image[]',
                'contents' => $input->contents(),
                'filename' => 'reference-'.$index++.'.'.$this->extensionFor($input->mimeType()),
            ];
        }

        return $this->http->postMultipart(
            url: "{$this->config->baseUrl}/images/edits",
            headers: $this->headersWithoutContentType(),
            data: $fields,
            attachments: $attachments,
            timeout: $this->config->mediaTimeout,
        );
    }

    /**
     * Parse the provider response (identical shape for generate + edit) into an
     * ImageResponse.
     *
     * @param  array<string, mixed>  $data
     */
    private function parse(array $data, ImageRequest $request): ImageResponse
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $data['data'] ?? [];

        $format = $this->resolveFormat($request);

        if ($request->count === 1) {
            $first = $results[0] ?? [];
            $base64 = $this->base64For($first);

            return new ImageResponse(
                url: $this->referenceFor($first, $base64, $format),
                revisedPrompt: isset($first['revised_prompt']) ? (string) $first['revised_prompt'] : null,
                meta: ['model' => $request->model],
                base64: $base64,
                format: $base64 !== null ? $format : null,
            );
        }

        // Only the first result is surfaced on the base64 property; callers
        // needing every payload should decode from the urls array (data URIs).
        $firstBase64 = $this->base64For($results[0] ?? []);

        $urls = array_map(
            fn (array $item): string => $this->referenceFor($item, $this->base64For($item), $format),
            $results,
        );

        $revisedPrompt = isset($results[0]['revised_prompt'])
            ? (string) $results[0]['revised_prompt']
            : null;

        return new ImageResponse(
            url: $urls,
            revisedPrompt: $revisedPrompt,
            meta: ['model' => $request->model, 'count' => count($results)],
            base64: $firstBase64,
            format: $firstBase64 !== null ? $format : null,
        );
    }

    /**
     * A file extension for the multipart upload filename, derived from the mime
     * type (the provider keys edits on the uploaded file, not the name).
     */
    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    /**
     * Extract the base64 payload for a result item, but only when no hosted URL
     * is present — a real URL always wins so storage resolves it over inline data.
     *
     * @param  array<string, mixed>  $item
     */
    private function base64For(array $item): ?string
    {
        if (isset($item['url'])) {
            return null;
        }

        return isset($item['b64_json']) ? (string) $item['b64_json'] : null;
    }

    /**
     * Build the URL reference for a result item. Prefers a hosted URL; when the
     * provider returns base64 (b64_json) — the only mode for gpt-image-* models —
     * falls back to a data URI so the value remains a usable, renderable reference.
     *
     * @param  array<string, mixed>  $item
     */
    private function referenceFor(array $item, ?string $base64, string $format): string
    {
        if (isset($item['url'])) {
            return (string) $item['url'];
        }

        if ($base64 !== null) {
            return "data:image/{$format};base64,{$base64}";
        }

        return '';
    }

    /**
     * Resolve the output image format from the request or provider options,
     * defaulting to png (the OpenAI image default).
     */
    private function resolveFormat(ImageRequest $request): string
    {
        if ($request->format !== null) {
            return $request->format;
        }

        $option = $request->providerOptions['output_format'] ?? null;

        return is_string($option) ? $option : 'png';
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('imageToText', 'openai');
    }
}
