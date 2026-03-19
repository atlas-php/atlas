<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

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
 * OpenAI video handler using the Sora API with async polling.
 *
 * Submits a generation request to POST /v1/videos, polls GET /v1/videos/{id}
 * until completed, then downloads the video binary from GET /v1/videos/{id}/content.
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
        ], fn (mixed $v): bool => $v !== null);

        if ($request->duration !== null) {
            $body['seconds'] = (string) $request->duration;
        }

        if ($request->ratio !== null) {
            $body['size'] = $this->resolveSize($request->ratio);
        }

        /** @var Input|null $sourceImage */
        $sourceImage = $request->media[0] ?? null;

        if ($sourceImage !== null) {
            $body['input_reference'] = $this->resolveInputReference($sourceImage);
        }

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/videos",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        $videoId = (string) ($data['id'] ?? '');

        if ($videoId === '') {
            throw new ProviderException(
                provider: 'openai',
                model: $request->model,
                statusCode: 500,
                providerMessage: 'Video generation response missing id',
            );
        }

        $completedData = $this->pollForCompletion($videoId, $request->model);

        $seconds = isset($completedData['seconds']) ? (int) $completedData['seconds'] : null;

        // Download the video binary — OpenAI requires auth for the /content endpoint
        $binary = $this->http->getRaw(
            url: "{$this->config->baseUrl}/videos/{$videoId}/content",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->mediaTimeout,
        );

        $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'atlas_video_'.bin2hex(random_bytes(8)).'.mp4';
        $written = file_put_contents($tmpPath, $binary);

        if ($written === false) {
            throw new ProviderException(
                provider: 'openai',
                model: $request->model,
                statusCode: 500,
                providerMessage: 'Failed to write video to temporary file',
            );
        }

        return new VideoResponse(
            url: $tmpPath,
            duration: $seconds,
            meta: [
                'model' => $completedData['model'] ?? $request->model,
                'video_id' => $videoId,
                'size' => $completedData['size'] ?? null,
            ],
            format: 'mp4',
        );
    }

    public function videoToText(VideoRequest $request): TextResponse
    {
        throw UnsupportedFeatureException::make('videoToText', 'openai');
    }

    /**
     * Poll for video generation completion.
     *
     * @return array<string, mixed>
     */
    private function pollForCompletion(string $videoId, string $model): array
    {
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            if ($this->pollInterval > 0) {
                sleep($this->pollInterval);
            }

            $data = $this->http->get(
                url: "{$this->config->baseUrl}/videos/{$videoId}",
                headers: $this->headersWithoutContentType(),
                timeout: $this->config->timeout,
            );

            $status = (string) ($data['status'] ?? '');

            if ($status === 'completed') {
                return $data;
            }

            if ($status === 'failed') {
                $errorMessage = is_array($data['error'] ?? null)
                    ? (string) ($data['error']['message'] ?? 'Unknown error')
                    : (string) ($data['error'] ?? 'Unknown error');

                throw new ProviderException(
                    provider: 'openai',
                    model: $model,
                    statusCode: 422,
                    providerMessage: "Video generation failed for {$videoId}: {$errorMessage}",
                );
            }
        }

        throw new ProviderException(
            provider: 'openai',
            model: $model,
            statusCode: 408,
            providerMessage: "Video generation timed out after polling {$videoId} for ".($this->maxAttempts * $this->pollInterval).' seconds',
        );
    }

    /**
     * Resolve ratio/size string to OpenAI's size format.
     */
    private function resolveSize(string $ratio): string
    {
        if (str_contains($ratio, 'x')) {
            return $ratio;
        }

        return match ($ratio) {
            '16:9', 'landscape' => '1280x720',
            '9:16', 'portrait' => '720x1280',
            default => $ratio,
        };
    }

    /**
     * Resolve an Input object to an input_reference for image-to-video.
     *
     * @return array<string, mixed>
     */
    private function resolveInputReference(Input $input): array
    {
        if ($input->isUrl()) {
            return ['image_url' => $input->url()];
        }

        if ($input->isBase64()) {
            return ['image_url' => "data:{$input->mimeType()};base64,".$input->data()];
        }

        if ($input->isPath()) {
            $raw = file_get_contents($input->path());

            if ($raw === false) {
                throw new ProviderException(
                    provider: 'openai',
                    model: 'video',
                    statusCode: 400,
                    providerMessage: "Cannot read image file: {$input->path()}",
                );
            }

            return ['image_url' => "data:{$input->mimeType()};base64,".base64_encode($raw)];
        }

        throw new ProviderException(
            provider: 'openai',
            model: 'video',
            statusCode: 400,
            providerMessage: 'Cannot resolve image input — no supported source set.',
        );
    }
}
