<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Prism\Prism\ValueObjects\Media\Audio;

/**
 * Service for text-to-speech and speech-to-text operations.
 *
 * Provides a fluent API for speech operations. Uses clone pattern for immutability.
 */
class SpeechService
{
    private ?string $provider = null;

    private ?string $model = null;

    private ?string $transcriptionModel = null;

    private ?string $voice = null;

    private ?string $format = null;

    public function __construct(
        private readonly PrismBuilderContract $prismBuilder,
        private readonly ProviderConfigService $configService,
    ) {}

    /**
     * Set the provider for speech operations.
     */
    public function using(string $provider): self
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    /**
     * Set the model for text-to-speech operations.
     */
    public function model(string $model): self
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    /**
     * Set the model for speech-to-text (transcription) operations.
     */
    public function transcriptionModel(string $model): self
    {
        $clone = clone $this;
        $clone->transcriptionModel = $model;

        return $clone;
    }

    /**
     * Set the voice for text-to-speech.
     */
    public function voice(string $voice): self
    {
        $clone = clone $this;
        $clone->voice = $voice;

        return $clone;
    }

    /**
     * Set the output format for audio.
     */
    public function format(string $format): self
    {
        $clone = clone $this;
        $clone->format = $format;

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
        $speechConfig = $this->configService->getSpeechConfig();
        $provider = $this->provider ?? $options['provider'] ?? $speechConfig['provider'];
        $model = $this->model ?? $options['model'] ?? $speechConfig['model'];
        $format = $this->format ?? $options['format'] ?? 'mp3';

        $requestOptions = array_filter([
            'voice' => $this->voice ?? $options['voice'] ?? null,
            'format' => $format,
        ]);

        $request = $this->prismBuilder->forSpeech($provider, $model, $text, $requestOptions);
        $response = $request->asAudio();

        return [
            'audio' => $response->audio ?? '',
            'format' => $format,
        ];
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
        $speechConfig = $this->configService->getSpeechConfig();
        $provider = $this->provider ?? $options['provider'] ?? $speechConfig['provider'];
        $model = $this->transcriptionModel ?? $options['model'] ?? $speechConfig['transcription_model'];

        $audioObject = $audio instanceof Audio
            ? $audio
            : Audio::fromLocalPath($audio);

        $request = $this->prismBuilder->forTranscription($provider, $model, $audioObject, $options);
        $response = $request->asText();

        return [
            'text' => $response->text ?? '',
            'language' => $response->language ?? null,
            'duration' => $response->duration ?? null,
        ];
    }
}
