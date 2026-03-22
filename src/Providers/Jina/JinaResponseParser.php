<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Jina;

use Atlasphp\Atlas\Providers\Concerns\ResolvesRerankDocuments;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;

/**
 * Parses Jina rerank API responses into RerankResponse objects.
 */
class JinaResponseParser
{
    use ResolvesRerankDocuments;

    /**
     * Parse a Jina rerank response.
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
        if (isset($data['usage'])) {
            $meta['usage'] = $data['usage'];
        }

        return new RerankResponse($rerankResults, $meta);
    }
}
