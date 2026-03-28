<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Cohere;

use Atlasphp\Atlas\Providers\Handlers\AbstractRerankHandler;
use Atlasphp\Atlas\Requests\RerankRequest;

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
     * @return array<string, mixed>
     */
    protected function parseMeta(array $data): array
    {
        $meta = [];

        if (isset($data['id'])) {
            $meta['id'] = $data['id'];
        }

        if (isset($data['meta']['api_version'])) {
            $meta['api_version'] = $data['meta']['api_version'];
        }

        if (isset($data['meta']['billed_units'])) {
            $meta['billed_units'] = $data['meta']['billed_units'];
        }

        return $meta;
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
