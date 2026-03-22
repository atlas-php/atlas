<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;

/**
 * Shared rerank handler with provider-specific endpoint and parsing.
 *
 * Subclasses provide the endpoint path and response parser.
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

            $lines = [];
            foreach ($doc as $key => $value) {
                $lines[] = "{$key}: {$value}";
            }

            return implode("\n", $lines);
        }, $documents);
    }

    /**
     * The provider's rerank endpoint URL.
     */
    abstract protected function endpoint(): string;

    /**
     * Parse the provider response into a RerankResponse.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string|array<string, string>>  $documents
     */
    abstract protected function parseResponse(array $data, array $documents): RerankResponse;

    /**
     * Add provider-specific fields to the request body.
     *
     * @param  array<string, mixed>  $body
     */
    protected function appendProviderBody(RerankRequest $request, array &$body): void
    {
        // Override in subclasses for provider-specific fields
    }
}
