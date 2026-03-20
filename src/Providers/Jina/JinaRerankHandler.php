<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Jina;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\RerankHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;

/**
 * Jina rerank handler using the /v1/rerank endpoint.
 */
class JinaRerankHandler implements RerankHandler
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

        $body = array_merge($body, $request->providerOptions);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v1/rerank",
            headers: $this->headers(),
            body: $body,
            timeout: $this->config->timeout,
        );

        return JinaResponseParser::parse($data, $request->documents);
    }

    /**
     * Flatten structured documents to strings for the Jina API.
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
