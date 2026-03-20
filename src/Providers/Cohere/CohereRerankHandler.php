<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Cohere;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\RerankHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;

/**
 * Cohere rerank handler using the /v2/rerank endpoint.
 */
class CohereRerankHandler implements RerankHandler
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

        if ($request->maxTokensPerDoc !== null) {
            $body['max_tokens_per_doc'] = $request->maxTokensPerDoc;
        }

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v2/rerank",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        return CohereResponseParser::parse($data, $request->documents);
    }

    /**
     * Format documents for the Cohere API.
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

            $lines = [];
            foreach ($doc as $key => $value) {
                $lines[] = "{$key}: {$value}";
            }

            return implode("\n", $lines);
        }, $documents);
    }
}
