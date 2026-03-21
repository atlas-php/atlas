<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Gemini provider handler for the models endpoint.
 *
 * Uses Gemini's /v1beta/models format which returns models[].name
 * instead of OpenAI's data[].id format.
 */
class Provider extends AbstractProviderHandler
{
    /**
     * Google uses x-goog-api-key header.
     *
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        return [
            'x-goog-api-key' => $this->config->apiKey,
        ];
    }

    /**
     * Fetch models from Gemini's /v1beta/models endpoint.
     */
    protected function fetchModels(): ModelList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/v1beta/models?pageSize=1000",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $models */
        $models = $data['models'] ?? [];

        $ids = array_map(function (array $model): string {
            $name = (string) ($model['name'] ?? '');

            // Strip "models/" prefix: "models/gemini-2.5-flash" → "gemini-2.5-flash"
            return str_starts_with($name, 'models/') ? substr($name, 7) : $name;
        }, $models);

        sort($ids);

        return new ModelList($ids);
    }

    protected function fetchVoices(): VoiceList
    {
        return new VoiceList([]);
    }
}
