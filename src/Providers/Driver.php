<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\ProviderContext;
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
use Closure;
use Illuminate\Http\Client\RequestException;

/**
 * Abstract driver that coordinates modality handlers for a provider.
 *
 * Every modality call routes through dispatch() which applies provider
 * middleware from global config and the request object.
 */
abstract class Driver
{
    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly ?MiddlewareStack $middlewareStack = null,
    ) {}

    // ─── Modality Methods ────────────────────────────────────────────────

    public function text(TextRequest $request): TextResponse
    {
        return $this->dispatch('text', $request, fn (TextRequest $r) => $this->textHandler()->text($r));
    }

    public function stream(TextRequest $request): StreamResponse
    {
        return $this->dispatch('stream', $request, fn (TextRequest $r) => $this->textHandler()->stream($r));
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        return $this->dispatch('structured', $request, fn (TextRequest $r) => $this->textHandler()->structured($r));
    }

    public function image(ImageRequest $request): ImageResponse
    {
        return $this->dispatch('image', $request, fn (ImageRequest $r) => $this->imageHandler()->image($r));
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        return $this->dispatch('imageToText', $request, fn (ImageRequest $r) => $this->imageHandler()->imageToText($r));
    }

    public function audio(AudioRequest $request): AudioResponse
    {
        return $this->dispatch('audio', $request, fn (AudioRequest $r) => $this->audioHandler()->audio($r));
    }

    public function audioToText(AudioRequest $request): TextResponse
    {
        return $this->dispatch('audioToText', $request, fn (AudioRequest $r) => $this->audioHandler()->audioToText($r));
    }

    public function video(VideoRequest $request): VideoResponse
    {
        return $this->dispatch('video', $request, fn (VideoRequest $r) => $this->videoHandler()->video($r));
    }

    public function videoToText(VideoRequest $request): TextResponse
    {
        return $this->dispatch('videoToText', $request, fn (VideoRequest $r) => $this->videoHandler()->videoToText($r));
    }

    public function embed(EmbedRequest $request): EmbeddingsResponse
    {
        return $this->dispatch('embed', $request, fn (EmbedRequest $r) => $this->embedHandler()->embed($r));
    }

    public function moderate(ModerateRequest $request): ModerationResponse
    {
        return $this->dispatch('moderate', $request, fn (ModerateRequest $r) => $this->moderateHandler()->moderate($r));
    }

    // ─── Middleware Dispatch ─────────────────────────────────────────────

    /**
     * Dispatch a handler call through provider middleware.
     *
     * Merges middleware from global config and the request object.
     * Global config middleware runs outermost, request middleware innermost.
     */
    protected function dispatch(string $method, mixed $request, Closure $handler): mixed
    {
        if ($this->middlewareStack === null) {
            return $handler($request);
        }

        $middleware = array_merge(
            config('atlas.middleware.provider', []),
            $request->middleware,
        );

        if ($middleware === []) {
            return $handler($request);
        }

        $context = new ProviderContext(
            provider: $this->name(),
            model: $request->model,
            method: $method,
            request: $request,
        );

        return $this->middlewareStack->run(
            $context,
            $middleware,
            fn (ProviderContext $ctx) => $handler($ctx->request),
        );
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
