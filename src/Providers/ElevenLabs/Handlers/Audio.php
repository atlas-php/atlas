<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs\Handlers;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Input\Input;
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
    private const DEFAULT_VOICE_ID = '21m00Tcm4TlvDq8ikWAM'; // Rachel

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

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'xi-api-key' => $this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        return [
            'xi-api-key' => $this->config->apiKey,
        ];
    }

    /**
     * Extract the codec portion from an ElevenLabs output_format string.
     * e.g., 'mp3_44100_128' → 'mp3', 'pcm_16000' → 'pcm'
     */
    private function extractFormatCodec(string $format): string
    {
        return explode('_', $format)[0];
    }

    private function resolveAudioFile(Input $media): string
    {
        if ($media->isPath()) {
            $raw = file_get_contents($media->path());

            if ($raw === false) {
                throw new \InvalidArgumentException("Cannot read audio file: {$media->path()}");
            }

            return $raw;
        }

        if ($media->isBase64()) {
            return base64_decode($media->data());
        }

        if ($media->isUrl()) {
            $raw = file_get_contents($media->url());

            if ($raw === false) {
                throw new \InvalidArgumentException("Cannot fetch audio from URL: {$media->url()}");
            }

            return $raw;
        }

        throw new \InvalidArgumentException('Cannot resolve audio input — no supported source set.');
    }
}
