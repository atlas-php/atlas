<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Anthropic provider handler for the models endpoint.
 *
 * Uses Anthropic's /v1/models endpoint which returns data[].id format.
 * Custom auth via x-api-key header and anthropic-version.
 */
class Provider extends AbstractProviderHandler
{
    /**
     * Anthropic uses x-api-key header, not Bearer.
     *
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        return [
            'x-api-key' => $this->config->apiKey,
            'anthropic-version' => $this->config->extra['version'] ?? '2023-06-01',
        ];
    }

    /**
     * Fetch models from Anthropic's /v1/models endpoint.
     */
    protected function fetchModels(): ModelList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/models?limit=100",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $models */
        $models = $data['data'] ?? [];

        $ids = array_map(fn (array $model): string => (string) ($model['id'] ?? ''), $models);

        sort($ids);

        return new ModelList($ids);
    }

    protected function fetchVoices(): VoiceList
    {
        return new VoiceList([]);
    }
}
