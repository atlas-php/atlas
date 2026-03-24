<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs\Handlers;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\ResolvesAudioFile;
use Atlasphp\Atlas\Providers\ElevenLabs\BuildsElevenLabsHeaders;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Class Audio
 *
 * ElevenLabs audio handler for text-to-speech and speech-to-text.
 * TTS uses voice ID in the URL path with binary audio response.
 * STT uses multipart upload with JSON response.
 */
class Audio implements AudioHandler
{
    use BuildsElevenLabsHeaders, ResolvesAudioFile;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    /**
     * Text-to-Speech via POST /v1/text-to-speech/{voice_id}
     */
    public function audio(AudioRequest $request): AudioResponse
    {
        $voiceId = $request->voice ?? self::DEFAULT_VOICE_ID;

        $body = array_filter([
            'text' => $request->instructions,
            'model_id' => $request->model ?: 'eleven_multilingual_v2',
        ], fn (mixed $v): bool => $v !== null);

        // Voice settings
        $voiceSettings = [];

        if (isset($request->providerOptions['stability'])) {
            $voiceSettings['stability'] = $request->providerOptions['stability'];
        }

        if (isset($request->providerOptions['similarity_boost'])) {
            $voiceSettings['similarity_boost'] = $request->providerOptions['similarity_boost'];
        }

        if ($request->speed !== null) {
            $voiceSettings['speed'] = $request->speed;
        }

        if ($voiceSettings !== []) {
            $body['voice_settings'] = $voiceSettings;
        }

        if ($request->language !== null) {
            $body['language_code'] = $request->language;
        }

        // Passthrough provider options (excluding already-handled keys)
        $passthrough = array_diff_key(
            $request->providerOptions,
            array_flip(['stability', 'similarity_boost', 'output_format'])
        );
        $body = array_merge($body, $passthrough);

        $format = $request->format ?? $request->providerOptions['output_format'] ?? 'mp3_44100_128';
        $url = "{$this->config->baseUrl}/text-to-speech/{$voiceId}?output_format={$format}";

        $binary = $this->http->postRaw(
            url: $url,
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        return new AudioResponse(
            data: base64_encode($binary),
            format: $this->extractFormatCodec($format),
        );
    }

    /**
     * Speech-to-Text via POST /v1/speech-to-text
     */
    public function audioToText(AudioRequest $request): TextResponse
    {
        /** @var Input|null $media */
        $media = $request->media[0] ?? null;

        if ($media === null) {
            throw new \InvalidArgumentException('Audio input is required for transcription.');
        }

        $fileContents = $this->resolveAudioFile($media);

        $fields = array_filter([
            'model_id' => $request->model ?: 'scribe_v2',
            'language_code' => $request->language,
        ], fn (mixed $v): bool => $v !== null);

        $fields = array_merge($fields, $request->providerOptions);

        $data = $this->http->postMultipart(
            url: "{$this->config->baseUrl}/speech-to-text",
            headers: $this->headersWithoutContentType(),
            data: $fields,
            attachments: [
                ['name' => 'file', 'contents' => $fileContents, 'filename' => 'audio.mp3'],
            ],
            timeout: $this->config->mediaTimeout,
        );

        return new TextResponse(
            text: (string) ($data['text'] ?? ''),
            usage: new Usage(inputTokens: 0, outputTokens: 0),
            finishReason: FinishReason::Stop,
        );
    }
}
