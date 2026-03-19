<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Handlers\VideoHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;

/**
 * xAI video handler using async polling for video generation.
 *
 * Submits a generation request to POST /v1/videos/generations, then polls
 * GET /v1/videos/{id} until the status is `done` or `expired`.
 */
class Video implements VideoHandler
{
    use BuildsHeaders;

    private const DEFAULT_POLL_INTERVAL = 5;

    private const DEFAULT_MAX_ATTEMPTS = 120;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        private readonly int $pollInterval = self::DEFAULT_POLL_INTERVAL,
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
    ) {}

    public function video(VideoRequest $request): VideoResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'prompt' => $request->instructions,
            'duration' => $request->duration,
            'aspect_ratio' => $request->ratio,
        ], fn (mixed $v): bool => $v !== null);

        /** @var Input|null $sourceImage */
        $sourceImage = $request->media[0] ?? null;

        if ($sourceImage !== null) {
            $body['image'] = $this->resolveImageSource($sourceImage);
        }

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/videos/generations",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        $requestId = (string) ($data['request_id'] ?? $data['id'] ?? '');

        if ($requestId === '') {
            throw new ProviderException(
                provider: 'xai',
                model: $request->model,
                statusCode: 500,
                providerMessage: 'Video generation response missing request_id',
            );
        }

        return $this->pollForCompletion($requestId, $request->model);
    }

    public function videoToText(VideoRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('videoToText', 'xai');
    }

    /**
     * Poll for video generation completion.
     */
    private function pollForCompletion(string $requestId, string $model): VideoResponse
    {
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            if ($this->pollInterval > 0) {
                sleep($this->pollInterval);
            }

            $data = $this->http->get(
                url: "{$this->config->baseUrl}/videos/{$requestId}",
                headers: $this->headersWithoutContentType(),
                timeout: $this->config->timeout,
            );

            $status = (string) ($data['status'] ?? '');

            if ($status === 'done' || $status === 'completed') {
                /** @var array<string, mixed> $video */
                $video = $data['video'] ?? [];
                $url = (string) ($video['url'] ?? $data['url'] ?? $data['video_url'] ?? '');
                $duration = isset($video['duration']) ? (int) $video['duration']
                    : (isset($data['duration']) ? (int) $data['duration'] : null);

                return new VideoResponse(
                    url: $url,
                    duration: $duration,
                    meta: ['model' => $model, 'request_id' => $requestId],
                    format: $data['format'] ?? 'mp4',
                );
            }

            if ($status === 'expired' || $status === 'failed') {
                throw new ProviderException(
                    provider: 'xai',
                    model: $model,
                    statusCode: 422,
                    providerMessage: "Video generation {$status} for request {$requestId}",
                );
            }
        }

        throw new ProviderException(
            provider: 'xai',
            model: $model,
            statusCode: 408,
            providerMessage: "Video generation timed out after polling {$requestId} for ".($this->maxAttempts * $this->pollInterval).' seconds',
        );
    }

    /**
     * Resolve an Input object to an image source for the API.
     */
    private function resolveImageSource(Input $input): string
    {
        if ($input->isUrl()) {
            return $input->url();
        }

        if ($input->isBase64()) {
            return "data:{$input->mimeType()};base64,".$input->data();
        }

        if ($input->isPath()) {
            $raw = file_get_contents($input->path());

            if ($raw === false) {
                throw new ProviderException(
                    provider: 'xai',
                    model: 'video',
                    statusCode: 400,
                    providerMessage: "Cannot read image file: {$input->path()}",
                );
            }

            return "data:{$input->mimeType()};base64,".base64_encode($raw);
        }

        throw new ProviderException(
            provider: 'xai',
            model: 'video',
            statusCode: 400,
            providerMessage: 'Cannot resolve image input — no supported source set.',
        );
    }
}
