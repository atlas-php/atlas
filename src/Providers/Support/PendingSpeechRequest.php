<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Services\SpeechService;
use Prism\Prism\ValueObjects\Media\Audio;

/**
 * Fluent builder for speech operations.
 *
 * Provides a fluent API for configuring text-to-speech and speech-to-text
 * operations with metadata and retry support. Uses immutable cloning for
 * method chaining.
 */
final class PendingSpeechRequest
{
    use HasMetadataSupport;
    use HasProviderSupport;
    use HasRetrySupport;

    private ?string $transcriptionModel = null;

    private ?string $voice = null;

    private ?string $format = null;

    private ?float $speed = null;

    /**
     * Provider-specific options to pass through to Prism.
     *
     * @var array<string, mixed>
     */
    private array $providerOptions = [];

    public function __construct(
        private readonly SpeechService $speechService,
    ) {}

    /**
     * Set the provider for speech operations.
     *
     * Alias for withProvider() for convenience.
     */
    public function using(string $provider): static
    {
        return $this->withProvider($provider);
    }

    /**
     * Set the model for text-to-speech operations.
     *
     * Alias for withModel() for convenience.
     */
    public function model(string $model): static
    {
        return $this->withModel($model);
    }

    /**
     * Set the model for speech-to-text (transcription) operations.
     */
    public function transcriptionModel(string $model): static
    {
        $clone = clone $this;
        $clone->transcriptionModel = $model;

        return $clone;
    }

    /**
     * Set the voice for text-to-speech.
     */
    public function voice(string $voice): static
    {
        $clone = clone $this;
        $clone->voice = $voice;

        return $clone;
    }

    /**
     * Set the output format for audio.
     */
    public function format(string $format): static
    {
        $clone = clone $this;
        $clone->format = $format;

        return $clone;
    }

    /**
     * Set the speech speed (0.25 to 4.0 for OpenAI).
     */
    public function speed(float $speed): static
    {
        $clone = clone $this;
        $clone->speed = $speed;

        return $clone;
    }

    /**
     * Set provider-specific options.
     *
     * These options are passed directly to the provider via Prism's withProviderOptions().
     * Use this for provider-specific features like language, timbre, etc.
     *
     * @param  array<string, mixed>  $options  Provider-specific options.
     */
    public function withProviderOptions(array $options): static
    {
        $clone = clone $this;
        $clone->providerOptions = array_merge($clone->providerOptions, $options);

        return $clone;
    }

    /**
     * Convert text to speech.
     *
     * @param  string  $text  The text to convert.
     * @param  array<string, mixed>  $options  Additional options.
     * @return array{audio: string, format: string}
     */
    public function speak(string $text, array $options = []): array
    {
        $service = $this->buildConfiguredService();

        return $service->speak($text, $options);
    }

    /**
     * Transcribe audio to text (speech-to-text).
     *
     * @param  Audio|string  $audio  Audio object or file path.
     * @param  array<string, mixed>  $options  Additional options.
     * @return array{text: string, language: string|null, duration: float|null}
     */
    public function transcribe(Audio|string $audio, array $options = []): array
    {
        $service = $this->buildConfiguredService();

        return $service->transcribe($audio, $options);
    }

    /**
     * Build a configured SpeechService instance with all fluent settings applied.
     */
    private function buildConfiguredService(): SpeechService
    {
        $service = $this->speechService;

        $provider = $this->getProviderOverride();
        if ($provider !== null) {
            $service = $service->using($provider);
        }

        $model = $this->getModelOverride();
        if ($model !== null) {
            $service = $service->model($model);
        }

        if ($this->transcriptionModel !== null) {
            $service = $service->transcriptionModel($this->transcriptionModel);
        }

        if ($this->voice !== null) {
            $service = $service->voice($this->voice);
        }

        if ($this->format !== null) {
            $service = $service->format($this->format);
        }

        if ($this->speed !== null) {
            $service = $service->speed($this->speed);
        }

        if ($this->providerOptions !== []) {
            $service = $service->withProviderOptions($this->providerOptions);
        }

        if ($this->getMetadata() !== []) {
            $service = $service->withMetadata($this->getMetadata());
        }

        $retryConfig = $this->getRetryArray();
        if ($retryConfig !== null) {
            $service = $service->withRetry(...$retryConfig);
        }

        return $service;
    }
}
