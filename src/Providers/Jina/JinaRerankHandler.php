<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Jina;

use Atlasphp\Atlas\Providers\Handlers\AbstractRerankHandler;
use Atlasphp\Atlas\Responses\RerankResponse;

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
     * @param  array<int, string|array<string, string>>  $documents
     */
    protected function parseResponse(array $data, array $documents): RerankResponse
    {
        return JinaResponseParser::parse($data, $documents);
    }
}
