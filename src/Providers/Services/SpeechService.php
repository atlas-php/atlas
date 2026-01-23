<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Prism\Prism\ValueObjects\Media\Audio;
use RuntimeException;
use Throwable;

/**
 * Stateless service for text-to-speech and speech-to-text operations.
 *
 * Provides speech operations with pipeline middleware support.
 */
class SpeechService
{
    public function __construct(
        private readonly PrismBuilderContract $prismBuilder,
        private readonly ProviderConfigService $configService,
        private readonly PipelineRunner $pipelineRunner,
    ) {}

    /**
     * Convert text to speech.
     *
     * @param  string  $text  The text to convert.
     * @param  array<string, mixed>  $options  Options including provider, model, voice, format, speed, metadata, provider_options.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array{audio: string, format: string}
     */
    public function speak(string $text, array $options = [], ?array $retry = null): array
    {
        $speechConfig = $this->configService->getSpeechConfig();
        $provider = $options['provider'] ?? $speechConfig['provider'];
        $model = $options['model'] ?? $speechConfig['model'];
        $voice = $options['voice'] ?? null;
        $format = $options['format'] ?? 'mp3';
        $speed = $options['speed'] ?? null;
        $metadata = $options['metadata'] ?? [];
        $providerOptions = $options['provider_options'] ?? [];

        try {
            // Run before_speak pipeline
            $beforeData = [
                'text' => $text,
                'provider' => $provider,
                'model' => $model,
                'format' => $format,
                'voice' => $voice,
                'options' => $options,
                'metadata' => $metadata,
            ];

            /** @var array{text: string, provider: string, model: string, format: string, voice: string|null, options: array<string, mixed>, metadata: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'speech.before_speak',
                $beforeData,
            );

            $text = $beforeData['text'];
            $provider = $beforeData['provider'];
            $model = $beforeData['model'];
            $format = $beforeData['format'];

            // Build request options
            $requestOptions = array_filter([
                'voice' => $beforeData['voice'],
                'speed' => $speed,
            ]);

            // Merge with provider-specific options
            $requestOptions = array_merge($requestOptions, $providerOptions);

            // Use explicit retry config or fall back to config-based retry
            $retry = $retry ?? $this->configService->getRetryConfig();

            $request = $this->prismBuilder->forSpeech($provider, $model, $text, $requestOptions, $retry);
            $response = $request->asAudio();

            // Extract audio content from GeneratedAudio object
            $audioContent = '';
            if (isset($response->audio)) {
                $audio = $response->audio;
                if (property_exists($audio, 'base64') && $audio->base64) {
                    $decoded = base64_decode($audio->base64, true);
                    if ($decoded === false) {
                        throw new RuntimeException('Failed to decode audio base64 content: invalid base64 data');
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
                'metadata' => $metadata,
                'result' => $result,
            ];

            /** @var array{result: array{audio: string, format: string}} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'speech.after_speak',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $this->handleSpeakError($text, $provider, $model, $voice, $format, $metadata, $e);
            throw $e;
        }
    }

    /**
     * Transcribe audio to text (speech-to-text).
     *
     * @param  Audio|string  $audio  Audio object or file path.
     * @param  array<string, mixed>  $options  Options including provider, transcription_model, metadata, provider_options.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array{text: string, language: string|null, duration: float|null}
     */
    public function transcribe(Audio|string $audio, array $options = [], ?array $retry = null): array
    {
        $speechConfig = $this->configService->getSpeechConfig();
        $provider = $options['provider'] ?? $speechConfig['provider'];
        $model = $options['transcription_model'] ?? $speechConfig['transcription_model'];
        $metadata = $options['metadata'] ?? [];
        $providerOptions = $options['provider_options'] ?? [];

        try {
            // Run before_transcribe pipeline
            $beforeData = [
                'audio' => $audio,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
            ];

            /** @var array{audio: Audio|string, provider: string, model: string, options: array<string, mixed>, metadata: array<string, mixed>} $beforeData */
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

            // Merge provider options
            $requestOptions = array_merge($options, $providerOptions);

            // Use explicit retry config or fall back to config-based retry
            $retry = $retry ?? $this->configService->getRetryConfig();

            $request = $this->prismBuilder->forTranscription($provider, $model, $audioObject, $requestOptions, $retry);
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
                'metadata' => $metadata,
                'result' => $result,
            ];

            /** @var array{result: array{text: string, language: string|null, duration: float|null}} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'speech.after_transcribe',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $this->handleTranscribeError($audio, $provider, $model, $options, $metadata, $e);
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
     * @param  array<string, mixed>  $metadata  The metadata that was provided.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleSpeakError(
        string $text,
        string $provider,
        string $model,
        ?string $voice,
        string $format,
        array $metadata,
        Throwable $exception,
    ): void {
        $errorData = [
            'operation' => 'speak',
            'text' => $text,
            'provider' => $provider,
            'model' => $model,
            'voice' => $voice,
            'format' => $format,
            'metadata' => $metadata,
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
     * @param  array<string, mixed>  $metadata  The metadata that was provided.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleTranscribeError(
        Audio|string $audio,
        string $provider,
        string $model,
        array $options,
        array $metadata,
        Throwable $exception,
    ): void {
        $errorData = [
            'operation' => 'transcribe',
            'audio' => $audio,
            'provider' => $provider,
            'model' => $model,
            'options' => $options,
            'metadata' => $metadata,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('speech.on_error', $errorData);
    }
}
