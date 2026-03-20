<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Cohere;

use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;

/**
 * Parses Cohere rerank API responses into RerankResponse objects.
 */
class CohereResponseParser
{
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

    /**
     * Resolve the document text from the response or original request.
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, string|array<string, string>>  $originalDocuments
     */
    private static function resolveDocument(array $result, array $originalDocuments, int $index): string
    {
        if (isset($result['document']['text'])) {
            return (string) $result['document']['text'];
        }

        $original = $originalDocuments[$index] ?? '';

        if (is_array($original)) {
            $lines = [];
            foreach ($original as $key => $value) {
                $lines[] = "{$key}: {$value}";
            }

            return implode("\n", $lines);
        }

        return $original;
    }
}
