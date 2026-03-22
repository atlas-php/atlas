<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;

/**
 * Shared rerank handler with provider-specific endpoint and meta parsing.
 *
 * Subclasses provide the endpoint path and meta extraction.
 * Override appendProviderBody() to add provider-specific fields.
 */
abstract class AbstractRerankHandler implements RerankHandler
{
    use BuildsHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
    ) {}

    public function rerank(RerankRequest $request): RerankResponse
    {
        $body = [
            'model' => $request->model,
            'query' => $request->query,
            'documents' => $this->formatDocuments($request->documents),
        ];

        if ($request->topN !== null) {
            $body['top_n'] = $request->topN;
        }

        $this->appendProviderBody($request, $body);

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: $this->endpoint(),
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        return $this->parseResponse($data, $request->documents);
    }

    /**
     * Parse the provider response into a RerankResponse.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string|array<string, string>>  $documents
     */
    protected function parseResponse(array $data, array $documents): RerankResponse
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $data['results'] ?? [];

        $rerankResults = array_map(function (array $result) use ($documents): RerankResult {
            $index = (int) ($result['index'] ?? 0);
            $score = (float) ($result['relevance_score'] ?? 0.0);
            $document = $this->resolveDocument($result, $documents, $index);

            return new RerankResult($index, $score, $document);
        }, $results);

        return new RerankResponse($rerankResults, $this->parseMeta($data));
    }

    /**
     * Extract provider-specific metadata from the response.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function parseMeta(array $data): array
    {
        return [];
    }

    /**
     * Format documents for the rerank API.
     *
     * String documents pass through directly. Associative array documents
     * are converted to YAML-like key: value format.
     *
     * @param  array<int, string|array<string, string>>  $documents
     * @return array<int, string>
     */
    protected function formatDocuments(array $documents): array
    {
        return array_map(function (string|array $doc): string {
            if (is_string($doc)) {
                return $doc;
            }

            return $this->serializeDocument($doc);
        }, $documents);
    }

    /**
     * The provider's rerank endpoint URL.
     */
    abstract protected function endpoint(): string;

    /**
     * Add provider-specific fields to the request body.
     *
     * Called before providerOptions merge, so providerOptions take precedence.
     *
     * @param  array<string, mixed>  $body
     */
    protected function appendProviderBody(RerankRequest $request, array &$body): void
    {
        // Override in subclasses for provider-specific fields
    }

    /**
     * Resolve the document text from the response or original request.
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, string|array<string, string>>  $originalDocuments
     */
    protected function resolveDocument(array $result, array $originalDocuments, int $index): string
    {
        if (isset($result['document']['text'])) {
            return (string) $result['document']['text'];
        }

        $original = $originalDocuments[$index] ?? '';

        if (is_array($original)) {
            return $this->serializeDocument($original);
        }

        return $original;
    }

    /**
     * Serialize an associative array document to key: value lines.
     *
     * @param  array<string, string>  $doc
     */
    private function serializeDocument(array $doc): string
    {
        $lines = [];
        foreach ($doc as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }

        return implode("\n", $lines);
    }
}
