<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Cohere;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\RerankHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

/**
 * Cohere provider driver supporting reranking via the Cohere API.
 */
class CohereDriver extends Driver
{
    public function name(): string
    {
        return 'cohere';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(rerank: true),
            $this->config->capabilityOverrides,
        );
    }

    protected function rerankHandler(): RerankHandler
    {
        return new CohereRerankHandler($this->config, $this->http);
    }
}
