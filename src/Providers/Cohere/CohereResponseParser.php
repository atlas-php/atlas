<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Cohere;

use Atlasphp\Atlas\Providers\Concerns\ResolvesRerankDocuments;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;

/**
 * Parses Cohere rerank API responses into RerankResponse objects.
 */
class CohereResponseParser
{
    use ResolvesRerankDocuments;

    /**
     * Parse a Cohere rerank response.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string|array<string, string>>  $originalDocuments
     */
    public static function parse(array $data, array $originalDocuments): RerankResponse
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $data['results'] ?? [];

        $rerankResults = array_map(function (array $result) use ($originalDocuments): RerankResult {
            $index = (int) ($result['index'] ?? 0);
            $score = (float) ($result['relevance_score'] ?? 0.0);

            $document = self::resolveDocument($result, $originalDocuments, $index);

            return new RerankResult($index, $score, $document);
        }, $results);

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

        return new RerankResponse($rerankResults, $meta);
    }
}
