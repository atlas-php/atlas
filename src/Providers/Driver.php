<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\Handlers\ModerateHandler;
use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\Handlers\VideoHandler;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;
use Illuminate\Http\Client\RequestException;

/**
 * Abstract driver that coordinates modality handlers for a provider.
 *
 * Each provider extends this class and overrides the handler methods
 * for the modalities it supports.
 */
abstract class Driver
{
    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    // ─── Modality Methods ────────────────────────────────────────────────

    public function text(TextRequest $request): TextResponse
    {
        return $this->textHandler()->text($request);
    }

    public function stream(TextRequest $request): StreamResponse
    {
        return $this->textHandler()->stream($request);
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        return $this->textHandler()->structured($request);
    }

    public function image(ImageRequest $request): ImageResponse
    {
        return $this->imageHandler()->image($request);
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        return $this->imageHandler()->imageToText($request);
    }

    public function audio(AudioRequest $request): AudioResponse
    {
        return $this->audioHandler()->audio($request);
    }

    public function audioToText(AudioRequest $request): TextResponse
    {
        return $this->audioHandler()->audioToText($request);
    }

    public function video(VideoRequest $request): VideoResponse
    {
        return $this->videoHandler()->video($request);
    }

    public function videoToText(VideoRequest $request): TextResponse
    {
        return $this->videoHandler()->videoToText($request);
    }

    public function embed(EmbedRequest $request): EmbeddingsResponse
    {
        return $this->embedHandler()->embed($request);
    }

    public function moderate(ModerateRequest $request): ModerationResponse
    {
        return $this->moderateHandler()->moderate($request);
    }

    // ─── Handler Resolution ──────────────────────────────────────────────

    protected function textHandler(): TextHandler
    {
        throw UnsupportedFeatureException::make('text', $this->name());
    }

    protected function imageHandler(): ImageHandler
    {
        throw UnsupportedFeatureException::make('image', $this->name());
    }

    protected function audioHandler(): AudioHandler
    {
        throw UnsupportedFeatureException::make('audio', $this->name());
    }

    protected function videoHandler(): VideoHandler
    {
        throw UnsupportedFeatureException::make('video', $this->name());
    }

    protected function embedHandler(): EmbedHandler
    {
        throw UnsupportedFeatureException::make('embed', $this->name());
    }

    protected function moderateHandler(): ModerateHandler
    {
        throw UnsupportedFeatureException::make('moderate', $this->name());
    }

    // ─── Provider Interrogation ──────────────────────────────────────────

    public function models(): ModelList
    {
        return $this->providerHandler('models')->models();
    }

    public function voices(): VoiceList
    {
        return $this->providerHandler('voices')->voices();
    }

    public function validate(): bool
    {
        return $this->providerHandler('validate')->validate();
    }

    /**
     * Resolve the provider handler for interrogation endpoints.
     *
     * @throws UnsupportedFeatureException
     */
    protected function providerHandler(string $feature = 'provider'): ProviderHandler
    {
        throw UnsupportedFeatureException::make($feature, $this->name());
    }

    // ─── Capabilities & Identity ─────────────────────────────────────────

    abstract public function capabilities(): ProviderCapabilities;

    abstract public function name(): string;

    // ─── Error Handling ──────────────────────────────────────────────────

    /**
     * Map a request exception to the appropriate Atlas exception.
     */
    public function handleRequestException(string $model, RequestException $e): never
    {
        match ($e->response->status()) {
            401 => throw new AuthenticationException($this->name(), $e),
            403 => throw new AuthorizationException($this->name(), $model, $e),
            429 => throw RateLimitException::from($this->name(), $model, $e),
            default => throw ProviderException::from($this->name(), $model, $e),
        };
    }
}
