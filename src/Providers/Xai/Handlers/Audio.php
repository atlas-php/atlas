<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * xAI audio handler for text-to-speech via /v1/tts.
 *
 * xAI TTS uses a different endpoint and payload format than OpenAI:
 * POST /v1/tts with `text`, `voice_id`, and `language` parameters.
 * Speech-to-text is not supported.
 */
class Audio implements AudioHandler
{
    use BuildsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function audio(AudioRequest $request): AudioResponse
    {
        $body = array_filter([
            'model' => $request->model,
            'text' => $request->instructions,
            'voice_id' => $request->voice ?? 'eve',
            'language' => $request->language ?? 'en',
        ], fn (mixed $v): bool => $v !== null);

        $body = array_merge($body, $request->providerOptions);

        $binary = $this->http->postRaw(
            url: "{$this->config->baseUrl}/tts",
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
        throw UnsupportedFeatureException::make('audioToText', 'xai');
    }
}
