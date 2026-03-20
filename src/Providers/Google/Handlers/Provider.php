<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Gemini provider handler for the models endpoint.
 *
 * Uses Gemini's /v1beta/models format which returns models[].name
 * instead of OpenAI's data[].id format.
 */
class Provider implements ProviderHandler
{
    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function models(): ModelList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/v1beta/models?pageSize=1000",
            headers: [
                'x-goog-api-key' => $this->config->apiKey,
            ],
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

    public function voices(): VoiceList
    {
        return new VoiceList([]);
    }

    public function validate(): bool
    {
        $this->models();

        return true;
    }
}
