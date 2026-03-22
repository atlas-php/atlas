<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs\Handlers;

use Atlasphp\Atlas\Providers\ElevenLabs\BuildsElevenLabsHeaders;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;

/**
 * Class Music
 *
 * ElevenLabs music generation via POST /v1/music.
 * Accepts a text prompt or composition plan and returns binary audio.
 * Duration is in milliseconds (3000-600000ms) — Atlas passes seconds, we convert.
 */
class Music
{
    use BuildsElevenLabsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    /**
     * Generate music from a text prompt or composition plan.
     */
    public function audio(AudioRequest $request): AudioResponse
    {
        $body = [];

        // Composition plan takes priority if provided
        if (isset($request->providerOptions['composition_plan'])) {
            $body['composition_plan'] = $request->providerOptions['composition_plan'];
        } else {
            if ($request->instructions === null) {
                throw new \InvalidArgumentException('Music generation requires instructions or a composition_plan via providerOptions.');
            }

            $body['prompt'] = $request->instructions;
        }

        // Duration: Atlas uses seconds, ElevenLabs uses milliseconds
        if ($request->duration !== null) {
            $body['music_length_ms'] = $request->duration * 1000;
        }

        // Music-specific options
        if (isset($request->providerOptions['strict_section_timing'])) {
            $body['strict_section_timing'] = (bool) $request->providerOptions['strict_section_timing'];
        }

        // Passthrough options (excluding already-handled keys)
        $passthrough = array_diff_key(
            $request->providerOptions,
            array_flip(['composition_plan', 'strict_section_timing', 'output_format'])
        );
        $body = array_merge($body, $passthrough);

        // Remove null values
        $body = array_filter($body, fn (mixed $v): bool => $v !== null);

        $format = $request->format ?? $request->providerOptions['output_format'] ?? 'mp3_44100_128';
        $url = "{$this->config->baseUrl}/music?output_format={$format}";

        $binary = $this->http->postRaw(
            url: $url,
            headers: $this->headers(),
            body: $body,
            timeout: max($this->config->mediaTimeout, 300),
        );

        return new AudioResponse(
            data: base64_encode($binary),
            format: $this->extractFormatCodec($format),
        );
    }
}
