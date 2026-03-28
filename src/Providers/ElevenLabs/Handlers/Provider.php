<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ElevenLabs\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Class Provider
 *
 * ElevenLabs provider handler for models and voices endpoints.
 * Uses xi-api-key auth. Cache key includes API key hash since
 * voice lists are per-account (clones, designs, library selections).
 */
class Provider extends AbstractProviderHandler
{
    /**
     * ElevenLabs auth uses xi-api-key header, not Bearer.
     *
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        return [
            'xi-api-key' => $this->config->apiKey,
        ];
    }

    /**
     * Fetch models from GET /v1/models.
     *
     * ElevenLabs returns a flat array (not wrapped in data[]).
     * Each model has 'model_id' instead of 'id'.
     */
    protected function fetchModels(): ModelList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/models",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        // ElevenLabs returns a flat array of model objects (not wrapped in data[])
        /** @var array<int, array<string, mixed>> $models */
        $models = array_values($data);

        $ids = array_map(
            fn (array $m): string => (string) ($m['model_id'] ?? ''),
            $models,
        );

        sort($ids);

        return new ModelList($ids);
    }

    /**
     * Fetch voices from GET /v1/voices.
     */
    protected function fetchVoices(): VoiceList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/voices",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        $voices = array_map(
            fn (array $v): string => (string) $v['voice_id'],
            $data['voices'] ?? [],
        );

        sort($voices);

        return new VoiceList($voices);
    }

    /**
     * Per-account cache key since ElevenLabs voices vary by API key.
     */
    protected function cacheKeyPrefix(): string
    {
        return 'elevenlabs:'.substr(md5($this->config->apiKey), 0, 8);
    }
}
