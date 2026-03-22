<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs;

/**
 * Shared header builder and format utilities for ElevenLabs handlers.
 *
 * ElevenLabs uses xi-api-key authentication instead of Bearer tokens.
 */
trait BuildsElevenLabsHeaders
{
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
    protected function extractFormatCodec(string $format): string
    {
        return explode('_', $format)[0];
    }
}
