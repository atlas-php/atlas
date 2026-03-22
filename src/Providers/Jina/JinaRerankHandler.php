<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Jina;

use Atlasphp\Atlas\Providers\Handlers\AbstractRerankHandler;

/**
 * Jina rerank handler using the /v1/rerank endpoint.
 */
class JinaRerankHandler extends AbstractRerankHandler
{
    protected function endpoint(): string
    {
        return "{$this->config->baseUrl}/v1/rerank";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function parseMeta(array $data): array
    {
        return isset($data['usage']) ? ['usage' => $data['usage']] : [];
    }
}
