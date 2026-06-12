<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Handlers\BatchHandler;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch as BatchRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;

/**
 * OpenAI batch handler using the /v1/batches endpoint.
 *
 * Serializes each line into JSONL via the synchronous handlers' own payload
 * builders (so a batch line is byte-identical to a live request), uploads it
 * through the Files API, and submits the batch. Results are parsed back through
 * those same handlers' parsers.
 */
class Batch implements BatchHandler
{
    use BuildsHeaders, HasOrganizationHeader {
        HasOrganizationHeader::extraHeaders insteadof BuildsHeaders;
    }

    /** Maps a batchable modality to its synchronous endpoint path. */
    private const ENDPOINTS = [
        'text' => '/v1/responses',
        'embed' => '/v1/embeddings',
    ];

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly Text $text,
        protected readonly Embed $embed,
    ) {}

    public function submit(BatchRequest $batch): BatchResponse
    {
        $fileId = $this->uploadInputFile($this->serialize($batch));

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/batches",
            headers: $this->headers(),
            body: [
                'input_file_id' => $fileId,
                'endpoint' => self::ENDPOINTS[$batch->modality->value],
                'completion_window' => $batch->completionWindow,
            ],
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
        $outputFileId = $batch['output_file_id'] ?? null;

        if (! is_string($outputFileId) || $outputFileId === '') {
            return [];
        }

        $modality = $this->modalityFromEndpoint((string) ($batch['endpoint'] ?? ''));
        $body = $this->http->getRaw(
            "{$this->config->baseUrl}/files/{$outputFileId}/content",
            $this->headers(),
            $this->config->timeout,
        );

        return $this->parseResults($body, $modality);
    }

    public function cancel(string $batchId): BatchResponse
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/batches/{$batchId}/cancel",
            headers: $this->headers(),
            body: [],
            timeout: $this->config->timeout,
            context: new ProviderRequestContext($this->config->provider, 'batch'),
        );

        return $this->toResponse($data);
    }

    /**
     * Build the JSONL payload, one line per request.
     */
    private function serialize(BatchRequest $batch): string
    {
        $url = self::ENDPOINTS[$batch->modality->value];

        $lines = array_map(function ($line) use ($batch, $url): string {
            return json_encode([
                'custom_id' => $line->customId,
                'method' => 'POST',
                'url' => $url,
                'body' => $this->bodyFor($batch->modality, $line->request),
            ], JSON_THROW_ON_ERROR);
        }, $batch->lines);

        return implode("\n", $lines);
    }

    /**
     * Build a line body by delegating to the synchronous handler's builder.
     *
     * @return array<string, mixed>
     */
    private function bodyFor(Modality $modality, object $request): array
    {
        if ($modality === Modality::Embed && $request instanceof EmbedRequest) {
            return $this->embed->buildBody($request);
        }

        if ($modality === Modality::Text && $request instanceof TextRequest) {
            return $this->text->buildPayload($request);
        }

        throw BatchException::mixedModality($modality->value, $request::class);
    }

    /**
     * Upload the JSONL as a batch input file and return its id.
     */
    private function uploadInputFile(string $jsonl): string
    {
        $data = $this->http->postMultipart(
            url: "{$this->config->baseUrl}/files",
            headers: $this->headersWithoutContentType(),
            data: ['purpose' => 'batch'],
            attachments: [[
                'name' => 'file',
                'contents' => $jsonl,
                'filename' => 'batch.jsonl',
            ]],
            timeout: $this->config->timeout,
            context: new ProviderRequestContext($this->config->provider, 'batch'),
        );

        $id = (string) ($data['id'] ?? '');

        if ($id === '') {
            throw new BatchException('Batch input-file upload returned no file id; cannot submit the batch.');
        }

        return $id;
    }

    /**
     * Fetch the raw batch object.
     *
     * @return array<string, mixed>
     */
    private function fetch(string $batchId): array
    {
        return $this->http->get(
            "{$this->config->baseUrl}/batches/{$batchId}",
            $this->headers(),
            $this->config->timeout,
        );
    }

    /**
     * Parse the downloaded output JSONL into per-line results.
     *
     * @return iterable<int, BatchResult>
     */
    private function parseResults(string $body, Modality $modality): iterable
    {
        $results = [];

        foreach (explode("\n", trim($body)) as $line) {
            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed> $row */
            $row = json_decode($line, true) ?: [];
            $customId = (string) ($row['custom_id'] ?? '');

            if (! empty($row['error'])) {
                $message = is_array($row['error']) ? (string) ($row['error']['message'] ?? 'batch line failed') : (string) $row['error'];
                $results[] = new BatchResult($customId, BatchResultStatus::Errored, error: new BatchException($message));

                continue;
            }

            /** @var array<string, mixed> $response */
            $response = is_array($row['response'] ?? null) ? $row['response'] : [];
            /** @var array<string, mixed> $payload */
            $payload = is_array($response['body'] ?? null) ? $response['body'] : [];

            // A line can carry error: null yet a non-2xx HTTP status with an
            // error body — treat that as a failed line, not a parsed success.
            $statusCode = (int) ($response['status_code'] ?? 200);
            if ($statusCode >= 400) {
                $message = is_array($payload['error'] ?? null) ? (string) ($payload['error']['message'] ?? "HTTP {$statusCode}") : "HTTP {$statusCode}";
                $results[] = new BatchResult($customId, BatchResultStatus::Errored, error: new BatchException($message));

                continue;
            }

            $parsed = $modality === Modality::Embed
                ? $this->embed->parse($payload)
                : $this->text->parse($payload);

            $results[] = new BatchResult($customId, BatchResultStatus::Succeeded, response: $parsed, usage: $parsed->usage);
        }

        return $results;
    }

    /**
     * Map a raw batch object to a BatchResponse.
     *
     * @param  array<string, mixed>  $data
     */
    private function toResponse(array $data): BatchResponse
    {
        /** @var array<string, mixed> $counts */
        $counts = is_array($data['request_counts'] ?? null) ? $data['request_counts'] : [];
        $total = (int) ($counts['total'] ?? 0);
        $succeeded = (int) ($counts['completed'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);

        return new BatchResponse(
            batchId: (string) ($data['id'] ?? ''),
            status: $this->mapStatus((string) ($data['status'] ?? '')),
            counts: new RequestCounts(
                total: $total,
                succeeded: $succeeded,
                failed: $failed,
                processing: max(0, $total - $succeeded - $failed),
            ),
            inputFileId: isset($data['input_file_id']) ? (string) $data['input_file_id'] : null,
            outputFileId: isset($data['output_file_id']) ? (string) $data['output_file_id'] : null,
            error: $this->extractError($data),
        );
    }

    /**
     * Pull the first job-level error message from a batch object, if any.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractError(array $data): ?string
    {
        $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
        $first = is_array($errors['data'][0] ?? null) ? $errors['data'][0] : null;

        return $first !== null && isset($first['message']) ? (string) $first['message'] : null;
    }

    /**
     * Map OpenAI's batch status vocabulary to the normalized enum.
     */
    private function mapStatus(string $status): BatchStatus
    {
        return match ($status) {
            'validating' => BatchStatus::Validating,
            'in_progress' => BatchStatus::InProgress,
            'finalizing' => BatchStatus::Finalizing,
            'completed' => BatchStatus::Completed,
            'failed' => BatchStatus::Failed,
            'expired' => BatchStatus::Expired,
            'cancelling' => BatchStatus::Cancelling,
            'cancelled' => BatchStatus::Cancelled,
            default => BatchStatus::InProgress,
        };
    }

    private function modalityFromEndpoint(string $endpoint): Modality
    {
        return str_contains($endpoint, 'embeddings') ? Modality::Embed : Modality::Text;
    }
}
