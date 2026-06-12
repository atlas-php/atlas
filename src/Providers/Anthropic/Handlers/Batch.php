<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic\Handlers;

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Providers\Handlers\BatchHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch as BatchRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;

/**
 * Anthropic batch handler using the Messages Batches API.
 *
 * Anthropic batches are submitted inline (no file upload): each line's params
 * are built by the synchronous text handler's own body builder, so a batch line
 * is identical to a live Messages request. Results stream from a results_url and
 * are parsed back through the text handler's parser.
 */
class Batch implements BatchHandler
{
    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly Text $text,
    ) {}

    public function submit(BatchRequest $batch): BatchResponse
    {
        $requests = array_map(function ($line): array {
            if (! $line->request instanceof TextRequest) {
                throw BatchException::notBatchable($line->request::class);
            }

            return [
                'custom_id' => $line->customId,
                'params' => $this->text->buildBody($line->request),
            ];
        }, $batch->lines);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/messages/batches",
            headers: $this->headers(),
            body: ['requests' => $requests],
            timeout: $this->config->timeout,
            context: new ProviderRequestContext($this->config->provider, 'batch'),
        );

        return $this->toResponse($data);
    }

    public function status(string $batchId): BatchResponse
    {
        return $this->toResponse($this->fetch($batchId));
    }

    public function results(string $batchId): iterable
    {
        $batch = $this->fetch($batchId);
        $resultsUrl = $batch['results_url'] ?? null;

        if (! is_string($resultsUrl) || $resultsUrl === '') {
            return [];
        }

        return $this->parseResults($this->http->getRaw($resultsUrl, $this->headers(), $this->config->timeout));
    }

    public function cancel(string $batchId): BatchResponse
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/messages/batches/{$batchId}/cancel",
            headers: $this->headers(),
            body: [],
            timeout: $this->config->timeout,
            context: new ProviderRequestContext($this->config->provider, 'batch'),
        );

        return $this->toResponse($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $batchId): array
    {
        return $this->http->get(
            "{$this->config->baseUrl}/messages/batches/{$batchId}",
            $this->headers(),
            $this->config->timeout,
        );
    }

    /**
     * @return iterable<int, BatchResult>
     */
    private function parseResults(string $body): iterable
    {
        $results = [];

        foreach (explode("\n", trim($body)) as $line) {
            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed> $row */
            $row = json_decode($line, true) ?: [];
            $customId = (string) ($row['custom_id'] ?? '');
            /** @var array<string, mixed> $result */
            $result = is_array($row['result'] ?? null) ? $row['result'] : [];
            $type = (string) ($result['type'] ?? 'errored');

            if ($type === 'succeeded' && is_array($result['message'] ?? null)) {
                $parsed = $this->text->parse($result['message']);
                $results[] = new BatchResult($customId, BatchResultStatus::Succeeded, response: $parsed, usage: $parsed->usage);

                continue;
            }

            $results[] = new BatchResult($customId, $this->mapResultStatus($type), error: new BatchException($this->resultError($result, $type)));
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resultError(array $result, string $type): string
    {
        $error = is_array($result['error'] ?? null) ? $result['error'] : [];

        return (string) ($error['message'] ?? "batch line {$type}");
    }

    private function mapResultStatus(string $type): BatchResultStatus
    {
        return match ($type) {
            'canceled' => BatchResultStatus::Cancelled,
            'expired' => BatchResultStatus::Expired,
            default => BatchResultStatus::Errored,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toResponse(array $data): BatchResponse
    {
        /** @var array<string, mixed> $counts */
        $counts = is_array($data['request_counts'] ?? null) ? $data['request_counts'] : [];
        $succeeded = (int) ($counts['succeeded'] ?? 0);
        $failed = (int) ($counts['errored'] ?? 0);
        $processing = (int) ($counts['processing'] ?? 0);
        $total = $processing + $succeeded + $failed + (int) ($counts['canceled'] ?? 0) + (int) ($counts['expired'] ?? 0);

        return new BatchResponse(
            batchId: (string) ($data['id'] ?? ''),
            status: $this->mapStatus((string) ($data['processing_status'] ?? '')),
            counts: new RequestCounts(total: $total, succeeded: $succeeded, failed: $failed, processing: $processing),
            outputFileId: isset($data['results_url']) ? (string) $data['results_url'] : null,
        );
    }

    /**
     * Map Anthropic's processing_status to the normalized enum. A finished batch
     * ("ended") is Completed; per-line failures live in the results.
     */
    private function mapStatus(string $status): BatchStatus
    {
        return match ($status) {
            'in_progress' => BatchStatus::InProgress,
            'canceling' => BatchStatus::Cancelling,
            'ended' => BatchStatus::Completed,
            default => BatchStatus::InProgress,
        };
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'x-api-key' => $this->config->apiKey,
            'anthropic-version' => $this->config->extra['version'] ?? '2023-06-01',
            'content-type' => 'application/json',
        ];
    }
}
