<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs\Handlers;

use Atlasphp\Atlas\Providers\ElevenLabs\BuildsElevenLabsHeaders;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;

/**
 * Class Sfx
 *
 * ElevenLabs sound effects generation via POST /v1/sound-generation.
 * Accepts a text prompt describing the desired sound and returns binary audio.
 */
class Sfx
{
    use BuildsElevenLabsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    /**
     * Generate a sound effect from a text description.
     */
    public function audio(AudioRequest $request): AudioResponse
    {
        if ($request->instructions === null) {
            throw new \InvalidArgumentException('Sound effect generation requires a text description via instructions().');
        }

        $body = array_filter([
            'text' => $request->instructions,
            'duration_seconds' => $request->duration !== null ? (float) $request->duration : null,
            'model_id' => $request->model ?: 'eleven_text_to_sound_v2',
        ], fn (mixed $v): bool => $v !== null);

        if (isset($request->providerOptions['loop'])) {
            $body['loop'] = (bool) $request->providerOptions['loop'];
        }

        if (isset($request->providerOptions['prompt_influence'])) {
            $body['prompt_influence'] = (float) $request->providerOptions['prompt_influence'];
        }

        $format = $request->format ?? $request->providerOptions['output_format'] ?? 'mp3_44100_128';
        $url = "{$this->config->baseUrl}/sound-generation?output_format={$format}";

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
}
