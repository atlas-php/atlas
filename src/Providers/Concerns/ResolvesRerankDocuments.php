<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

/**
 * Shared document resolution for rerank response parsers.
 *
 * Extracts document text from the provider response, falling back
 * to the original request documents when the response omits them.
 */
trait ResolvesRerankDocuments
{
    /**
     * Resolve the document text from the response or original request.
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, string|array<string, string>>  $originalDocuments
     */
    protected static function resolveDocument(array $result, array $originalDocuments, int $index): string
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
