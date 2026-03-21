<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic\Handlers;

use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Anthropic provider handler for the models endpoint.
 *
 * Uses Anthropic's /v1/models endpoint which returns data[].id format.
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
            url: "{$this->config->baseUrl}/models?limit=100",
            headers: [
                'x-api-key' => $this->config->apiKey,
                'anthropic-version' => $this->config->extra['version'] ?? '2023-06-01',
            ],
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $models */
        $models = $data['data'] ?? [];

        $ids = array_map(fn (array $model): string => (string) ($model['id'] ?? ''), $models);

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
