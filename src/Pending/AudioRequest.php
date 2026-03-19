<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Requests\AudioRequest as AudioRequestObject;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Fluent builder for audio generation and audio-to-text requests.
 */
class AudioRequest
{
    use HasMeta;
    use HasMiddleware;
    use ResolvesProvider;

    protected ?string $instructions = null;

    /** @var array<int, mixed> */
    protected array $media = [];

    protected ?string $voice = null;

    /** @var array<string, mixed>|null */
    protected ?array $voiceClone = null;

    protected ?float $speed = null;

    protected ?string $language = null;

    protected ?int $duration = null;

    protected ?string $format = null;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    public function __construct(
        protected Provider|string|null $provider,
        protected ?string $model,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $media
     */
    public function withMedia(array $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function withVoice(string $voice): static
    {
        $this->voice = $voice;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $voiceClone
     */
    public function withVoiceClone(array $voiceClone): static
    {
        $this->voiceClone = $voiceClone;

        return $this;
    }

    public function withSpeed(float $speed): static
    {
        $this->speed = $speed;

        return $this;
    }

    public function withLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function withDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptions = $options;

        return $this;
    }

    public function asAudio(): AudioResponse
    {
        $this->resolveAudioDefaults('tts');

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'audio');

        return $driver->audio($this->buildRequest());
    }

    public function asText(): TextResponse
    {
        $this->resolveAudioDefaults('stt');

        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'audioToText');

        return $driver->audioToText($this->buildRequest());
    }

    /**
     * Resolve provider and model from TTS or STT defaults if not set explicitly.
     *
     * Uses the same resolution pattern as AtlasManager::resolveDefaults().
     */
    protected function resolveAudioDefaults(string $modality): void
    {
        /** @var array<string, string|null> $defaults */
        $defaults = config("atlas.defaults.{$modality}", []);

        $this->provider = $this->provider ?? ($defaults['provider'] ?? null);
        $this->model = $this->model ?? ($defaults['model'] ?? null);

        if ($this->provider === null) {
            throw AtlasException::missingDefault($modality);
        }
    }

    public function buildRequest(): AudioRequestObject
    {
        return new AudioRequestObject(
            model: $this->model ?? '',
            instructions: $this->instructions,
            media: $this->media,
            voice: $this->voice,
            speed: $this->speed,
            language: $this->language,
            duration: $this->duration,
            format: $this->format,
            voiceClone: $this->voiceClone,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }
}
