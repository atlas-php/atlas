<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Jina;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\RerankHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;

/**
 * Jina provider driver supporting reranking via the Jina API.
 */
class JinaDriver extends Driver
{
    public function name(): string
    {
        return 'jina';
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
        return new JinaRerankHandler($this->config, $this->http);
    }
}
