<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Prism\Prism\ValueObjects\Media\Audio;
use Throwable;

/**
 * Service for text-to-speech and speech-to-text operations.
 *
 * Provides a fluent API for speech operations with pipeline middleware support.
 * Uses clone pattern for immutability.
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
        private readonly PipelineRunner $pipelineRunner,
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

        try {
            // Run before_speak pipeline
            $beforeData = [
                'text' => $text,
                'provider' => $provider,
                'model' => $model,
                'format' => $format,
                'voice' => $this->voice ?? $options['voice'] ?? null,
                'options' => $options,
            ];

            /** @var array{text: string, provider: string, model: string, format: string, voice: string|null, options: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'speech.before_speak',
                $beforeData,
            );

            $text = $beforeData['text'];
            $provider = $beforeData['provider'];
            $model = $beforeData['model'];
            $format = $beforeData['format'];

            $requestOptions = array_filter([
                'voice' => $beforeData['voice'],
                'format' => $format,
            ]);

            $request = $this->prismBuilder->forSpeech($provider, $model, $text, $requestOptions);
            $response = $request->asAudio();

            // Extract audio content from GeneratedAudio object
            $audioContent = '';
            if (isset($response->audio)) {
                $audio = $response->audio;
                if (property_exists($audio, 'base64') && $audio->base64) {
                    $decoded = base64_decode($audio->base64, true);
                    if ($decoded === false) {
                        throw new \RuntimeException('Failed to decode audio base64 content: invalid base64 data');
                    }
                    $audioContent = $decoded;
                } elseif (method_exists($audio, 'content')) {
                    $audioContent = $audio->content();
                }
            }

            $result = [
                'audio' => $audioContent,
                'format' => $format,
            ];

            // Run after_speak pipeline
            $afterData = [
                'text' => $text,
                'provider' => $provider,
                'model' => $model,
                'voice' => $beforeData['voice'],
                'format' => $format,
                'result' => $result,
            ];

            /** @var array{result: array{audio: string, format: string}} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'speech.after_speak',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $this->handleSpeakError(
                $text,
                $provider,
                $model,
                $this->voice ?? $options['voice'] ?? null,
                $format,
                $e,
            );
            throw $e;
        }
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

        try {
            // Run before_transcribe pipeline
            $beforeData = [
                'audio' => $audio,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
            ];

            /** @var array{audio: Audio|string, provider: string, model: string, options: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'speech.before_transcribe',
                $beforeData,
            );

            $audio = $beforeData['audio'];
            $provider = $beforeData['provider'];
            $model = $beforeData['model'];
            $options = $beforeData['options'];

            $audioObject = $audio instanceof Audio
                ? $audio
                : Audio::fromLocalPath($audio);

            $request = $this->prismBuilder->forTranscription($provider, $model, $audioObject, $options);
            $response = $request->asText();

            $result = [
                'text' => $response->text ?? '',
                'language' => $response->language ?? null,
                'duration' => $response->duration ?? null,
            ];

            // Run after_transcribe pipeline
            $afterData = [
                'audio' => $audio,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'result' => $result,
            ];

            /** @var array{result: array{text: string, language: string|null, duration: float|null}} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'speech.after_transcribe',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $this->handleTranscribeError($audio, $provider, $model, $options, $e);
            throw $e;
        }
    }

    /**
     * Handle a speak error by running the error pipeline.
     *
     * @param  string  $text  The text that was being converted.
     * @param  string  $provider  The provider that was used.
     * @param  string  $model  The model that was used.
     * @param  string|null  $voice  The voice that was requested.
     * @param  string  $format  The format that was requested.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleSpeakError(
        string $text,
        string $provider,
        string $model,
        ?string $voice,
        string $format,
        Throwable $exception,
    ): void {
        $errorData = [
            'operation' => 'speak',
            'text' => $text,
            'provider' => $provider,
            'model' => $model,
            'voice' => $voice,
            'format' => $format,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('speech.on_error', $errorData);
    }

    /**
     * Handle a transcribe error by running the error pipeline.
     *
     * @param  Audio|string  $audio  The audio that was being transcribed.
     * @param  string  $provider  The provider that was used.
     * @param  string  $model  The model that was used.
     * @param  array<string, mixed>  $options  The options that were provided.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleTranscribeError(
        Audio|string $audio,
        string $provider,
        string $model,
        array $options,
        Throwable $exception,
    ): void {
        $errorData = [
            'operation' => 'transcribe',
            'audio' => $audio,
            'provider' => $provider,
            'model' => $model,
            'options' => $options,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('speech.on_error', $errorData);
    }
}
