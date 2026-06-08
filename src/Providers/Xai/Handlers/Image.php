<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * xAI (Grok) image handler. Owned by xAI so its capabilities are not coupled to
 * another provider's wire format.
 *
 * Text-to-image goes to /v1/images/generations. Image-to-image goes to
 * /v1/images/edits with a JSON body — the reference media is passed inline as
 * `image_url` parts (a hosted URL or a base64 data URI), up to xAI's limit of
 * three source images. Unlike OpenAI's multipart edits endpoint, xAI's edits
 * endpoint expects application/json, which is why xAI needs its own handler.
 */
class Image implements ImageHandler
{
    use BuildsHeaders;

    /** xAI accepts at most this many source images per edit request. */
    private const MAX_SOURCE_IMAGES = 3;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function image(ImageRequest $request): ImageResponse
    {
        $references = $this->references($request);

        return $references === []
            ? $this->generate($request)
            : $this->edit($request, $references);
    }

    /**
     * Text-to-image via /images/generations.
     */
    private function generate(ImageRequest $request): ImageResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'prompt' => $request->instructions,
            'n' => $request->count,
        ], fn (mixed $v): bool => $v !== null);

        return $this->parse(
            $this->http->post(
                url: "{$this->config->baseUrl}/images/generations",
                headers: $this->headers(),
                body: array_merge($body, $request->providerOptions),
                timeout: $this->config->mediaTimeout,
            ),
            $request,
        );
    }

    /**
     * Image-to-image via /images/edits — JSON body with the reference media as
     * inline image_url parts (a single object for one source, an array for more).
     *
     * @param  array<int, array<string, string>>  $references
     */
    private function edit(ImageRequest $request, array $references): ImageResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'prompt' => $request->instructions,
            'n' => $request->count,
        ], fn (mixed $v): bool => $v !== null);

        $body['image'] = count($references) === 1 ? $references[0] : $references;

        return $this->parse(
            $this->http->post(
                url: "{$this->config->baseUrl}/images/edits",
                headers: $this->headers(),
                body: array_merge($body, $request->providerOptions),
                timeout: $this->config->mediaTimeout,
            ),
            $request,
        );
    }

    /**
     * The request's reference media as xAI image_url parts — a hosted URL passes
     * through, any other source becomes a base64 data URI. Capped at xAI's limit.
     *
     * @return array<int, array<string, string>>
     */
    private function references(ImageRequest $request): array
    {
        $refs = [];

        foreach ($request->media as $input) {
            if (! $input instanceof Input) {
                continue;
            }

            $url = $input->isUrl() && $input->url() !== null
                ? (string) $input->url()
                : 'data:'.$input->mimeType().';base64,'.base64_encode($input->contents());

            $refs[] = ['type' => 'image_url', 'url' => $url];

            if (count($refs) >= self::MAX_SOURCE_IMAGES) {
                break;
            }
        }

        return $refs;
    }

    /**
     * Parse the response (same shape for generation and edits): a hosted URL when
     * present, else an inline b64_json payload surfaced as a data URI.
     *
     * @param  array<string, mixed>  $data
     */
    private function parse(array $data, ImageRequest $request): ImageResponse
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $data['data'] ?? [];
        $first = $results[0] ?? [];

        $url = isset($first['url']) ? (string) $first['url'] : '';
        $base64 = (! isset($first['url']) && isset($first['b64_json'])) ? (string) $first['b64_json'] : null;

        if ($url === '' && $base64 !== null) {
            $url = "data:image/png;base64,{$base64}";
        }

        return new ImageResponse(
            url: $url,
            revisedPrompt: isset($first['revised_prompt']) ? (string) $first['revised_prompt'] : null,
            base64: $base64,
            meta: ['model' => $request->model],
            format: $base64 !== null ? 'png' : null,
        );
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('imageToText', 'xai');
    }
}
