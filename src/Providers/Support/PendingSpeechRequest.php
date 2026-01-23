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

    public function __construct(
        private readonly SpeechService $speechService,
    ) {}

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
     * Convert text to speech.
     *
     * @param  string  $text  The text to convert.
     * @param  array<string, mixed>  $options  Additional options.
     * @return array{audio: string, format: string}
     */
    public function speak(string $text, array $options = []): array
    {
        return $this->speechService->speak($text, $this->buildSpeakOptions($options), $this->getRetryArray());
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
        return $this->speechService->transcribe($audio, $this->buildTranscribeOptions($options), $this->getRetryArray());
    }

    /**
     * Build the options array for speak operations.
     *
     * @param  array<string, mixed>  $additionalOptions
     * @return array<string, mixed>
     */
    private function buildSpeakOptions(array $additionalOptions = []): array
    {
        $options = $additionalOptions;

        $provider = $this->getProviderOverride();
        if ($provider !== null) {
            $options['provider'] = $provider;
        }

        $model = $this->getModelOverride();
        if ($model !== null) {
            $options['model'] = $model;
        }

        if ($this->voice !== null) {
            $options['voice'] = $this->voice;
        }

        if ($this->format !== null) {
            $options['format'] = $this->format;
        }

        if ($this->speed !== null) {
            $options['speed'] = $this->speed;
        }

        $providerOptions = $this->getProviderOptions();
        if ($providerOptions !== []) {
            $options['provider_options'] = $providerOptions;
        }

        $metadata = $this->getMetadata();
        if ($metadata !== []) {
            $options['metadata'] = $metadata;
        }

        return $options;
    }

    /**
     * Build the options array for transcribe operations.
     *
     * @param  array<string, mixed>  $additionalOptions
     * @return array<string, mixed>
     */
    private function buildTranscribeOptions(array $additionalOptions = []): array
    {
        $options = $additionalOptions;

        $provider = $this->getProviderOverride();
        if ($provider !== null) {
            $options['provider'] = $provider;
        }

        if ($this->transcriptionModel !== null) {
            $options['transcription_model'] = $this->transcriptionModel;
        }

        $providerOptions = $this->getProviderOptions();
        if ($providerOptions !== []) {
            $options['provider_options'] = $providerOptions;
        }

        $metadata = $this->getMetadata();
        if ($metadata !== []) {
            $options['metadata'] = $metadata;
        }

        return $options;
    }
}
