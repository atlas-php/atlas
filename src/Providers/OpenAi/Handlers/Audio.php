<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * OpenAI audio handler for text-to-speech and speech-to-text.
 *
 * TTS uses /v1/audio/speech (binary response).
 * STT uses /v1/audio/transcriptions (multipart file upload).
 */
class Audio implements AudioHandler
{
    use BuildsHeaders, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function audio(AudioRequest $request): AudioResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'input' => $request->instructions,
            'voice' => $request->voice ?? 'alloy',
            'speed' => $request->speed,
            'response_format' => $request->format,
        ], fn (mixed $v): bool => $v !== null);

        $body = array_merge($body, $request->providerOptions);

        $binary = $this->http->postRaw(
            url: "{$this->config->baseUrl}/audio/speech",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->mediaTimeout,
        );

        return new AudioResponse(
            data: base64_encode($binary),
            format: $request->format ?? 'mp3',
        );
    }

    public function audioToText(AudioRequest $request): TextResponse
    {
        /** @var Input $media */
        $media = $request->media[0] ?? null;
        $fileContents = $this->resolveAudioFile($media);

        $fields = array_filter([
            'model' => $request->model,
            'language' => $request->language,
        ], fn (mixed $v): bool => $v !== null);

        $fields = array_merge($fields, $request->providerOptions);

        $filename = $this->resolveFilename($media, $request->format);

        $data = $this->http->postMultipart(
            url: "{$this->config->baseUrl}/audio/transcriptions",
            headers: $this->headersWithoutContentType(),
            data: $fields,
            attachments: [
                ['name' => 'file', 'contents' => $fileContents, 'filename' => $filename],
            ],
            timeout: $this->config->mediaTimeout,
        );

        return new TextResponse(
            text: (string) ($data['text'] ?? ''),
            usage: new Usage(inputTokens: 0, outputTokens: 0),
            finishReason: FinishReason::Stop,
        );
    }

    /**
     * Determine the filename to send with the multipart upload.
     */
    private function resolveFilename(?Input $media, ?string $format): string
    {
        if ($media !== null && $media->isPath()) {
            return basename($media->path());
        }

        $extension = $format ?? 'wav';

        return "audio.{$extension}";
    }

    /**
     * Resolve audio file contents from an Input object.
     */
    private function resolveAudioFile(?Input $media): string
    {
        if ($media === null) {
            throw new \InvalidArgumentException('Audio input is required for transcription.');
        }

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
