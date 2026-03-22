<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Cohere;

use Atlasphp\Atlas\Providers\Handlers\AbstractRerankHandler;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;

/**
 * Cohere rerank handler using the /v2/rerank endpoint.
 */
class CohereRerankHandler extends AbstractRerankHandler
{
    protected function endpoint(): string
    {
        return "{$this->config->baseUrl}/v2/rerank";
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string|array<string, string>>  $documents
     */
    protected function parseResponse(array $data, array $documents): RerankResponse
    {
        return CohereResponseParser::parse($data, $documents);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function appendProviderBody(RerankRequest $request, array &$body): void
    {
        if ($request->maxTokensPerDoc !== null) {
            $body['max_tokens_per_doc'] = $request->maxTokensPerDoc;
        }
    }
}
