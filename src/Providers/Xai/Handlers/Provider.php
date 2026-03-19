<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Providers\Handlers\ProviderHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * xAI provider handler for metadata endpoints.
 *
 * Lists available models via GET /v1/models and returns
 * the hardcoded list of available TTS voices.
 */
class Provider implements ProviderHandler
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

    public function voices(): VoiceList
    {
        return new VoiceList(['ara', 'eve', 'leo', 'rex', 'sal']);
    }

    public function validate(): bool
    {
        $this->models();

        return true;
    }
}
