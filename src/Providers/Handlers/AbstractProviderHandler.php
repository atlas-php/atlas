<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Base provider handler with shared models/validate logic.
 *
 * Subclasses only need to implement voices() with their provider's voice list.
 */
abstract class AbstractProviderHandler implements ProviderHandler
{
    use BuildsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function models(): ModelList
    {
        $data = $this->http->get(
            url: "{$this->config->baseUrl}/models",
            headers: $this->headersWithoutContentType(),
            timeout: $this->config->timeout,
        );

        /** @var array<int, array<string, mixed>> $models */
        $models = $data['data'] ?? [];

        $ids = array_map(fn (array $model): string => (string) $model['id'], $models);

        sort($ids);

        return new ModelList($ids);
    }

    abstract public function voices(): VoiceList;

    public function validate(): bool
    {
        $this->models();

        return true;
    }
}
