<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Handlers;

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\ProviderRequestContext;
use Atlasphp\Atlas\Providers\Google\Concerns\BuildsGoogleHeaders;
use Atlasphp\Atlas\Providers\Handlers\BatchHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch as BatchRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;

/**
 * Google (Gemini) batch handler using inline batchGenerateContent.
 *
 * Requests are submitted inline (no file upload); each line's request body is
 * built by the synchronous text handler. The model lives in the URL and applies
 * to every line, so a batch targets one model. The GET response is a
 * long-running Operation: state lives at metadata.state (JOB_STATE_*) and
 * results at response.inlinedResponses, correlated to each line by metadata.key.
 */
class Batch implements BatchHandler
{
    use BuildsGoogleHeaders;

    public function __construct(
        protected readonly ProviderConfig $config,
        protected readonly HttpClient $http,
        protected readonly Text $text,
    ) {}

    public function submit(BatchRequest $batch): BatchResponse
    {
        $model = $this->modelFor($batch);

        $requests = array_map(function ($line): array {
            if (! $line->request instanceof TextRequest) {
                throw BatchException::notBatchable($line->request::class);
            }

            return [
                'request' => $this->text->buildBody($line->request),
                'metadata' => ['key' => $line->customId],
            ];
        }, $batch->lines);

        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v1beta/models/{$model}:batchGenerateContent",
            headers: $this->headers(),
            body: ['batch' => [
                'display_name' => 'atlas-batch',
                'input_config' => ['requests' => ['requests' => $requests]],
            ]],
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
        $data = $this->fetch($batchId);
        /** @var array<int, array<string, mixed>> $inlined */
        $inlined = $data['response']['inlinedResponses']['inlinedResponses'] ?? [];

        $results = [];

        foreach ($inlined as $entry) {
            // Correlate to the submitted line by metadata.key — never by index
            // (Gemini may return inlinedResponses out of order).
            $key = (string) ($entry['metadata']['key'] ?? '');

            if (isset($entry['error'])) {
                $message = is_array($entry['error']) ? (string) ($entry['error']['message'] ?? 'batch line failed') : (string) $entry['error'];
                $results[] = new BatchResult($key, BatchResultStatus::Errored, error: new BatchException($message));

                continue;
            }

            /** @var array<string, mixed> $response */
            $response = is_array($entry['response'] ?? null) ? $entry['response'] : [];
            $parsed = $this->text->parse($response);
            $results[] = new BatchResult($key, BatchResultStatus::Succeeded, response: $parsed, usage: $parsed->usage);
        }

        return $results;
    }

    public function cancel(string $batchId): BatchResponse
    {
        $data = $this->http->post(
            url: "{$this->config->baseUrl}/v1beta/{$batchId}:cancel",
            headers: $this->headers(),
            body: [],
            timeout: $this->config->timeout,
            context: new ProviderRequestContext($this->config->provider, 'batch'),
        );

        return $data === [] ? $this->status($batchId) : $this->toResponse($data);
    }

    /**
     * The single model every line targets. Google applies one model to the
     * whole batch — it lives in the request URL, not per line — so all lines
     * must agree. Non-text lines are left for submit() to reject as not
     * batchable.
     *
     * @throws BatchException when the batch's text lines target more than one model.
     */
    private function modelFor(BatchRequest $batch): string
    {
        $model = null;

        foreach ($batch->lines as $line) {
            if (! $line->request instanceof TextRequest) {
                continue;
            }

            if ($model !== null && $line->request->model !== $model) {
                throw BatchException::mixedModel($model, $line->request->model);
            }

            $model ??= $line->request->model;
        }

        return $model ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $batchId): array
    {
        return $this->http->get(
            "{$this->config->baseUrl}/v1beta/{$batchId}",
            $this->headers(),
            $this->config->timeout,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toResponse(array $data): BatchResponse
    {
        $state = is_array($data['metadata'] ?? null) ? (string) ($data['metadata']['state'] ?? '') : '';
        /** @var array<int, mixed> $inlined */
        $inlined = $data['response']['inlinedResponses']['inlinedResponses'] ?? [];
        $succeeded = 0;
        $failed = 0;
        foreach ($inlined as $entry) {
            is_array($entry) && isset($entry['error']) ? $failed++ : $succeeded++;
        }

        return new BatchResponse(
            batchId: (string) ($data['name'] ?? ''),
            status: $this->mapStatus($state),
            counts: new RequestCounts(total: count($inlined), succeeded: $succeeded, failed: $failed),
            error: is_array($data['error'] ?? null) ? (string) ($data['error']['message'] ?? '') : null,
        );
    }

    /**
     * Map Gemini's job state to the normalized enum. Matches by suffix so it
     * tolerates both `JOB_STATE_*` (operation metadata) and `BATCH_STATE_*`
     * (resource) spellings the API inconsistently returns.
     *
     * Written as explicit `if` returns rather than `match (true)` so coverage
     * tools attribute each branch cleanly.
     */
    private function mapStatus(string $state): BatchStatus
    {
        if (str_ends_with($state, 'SUCCEEDED')) {
            return BatchStatus::Completed;
        }

        if (str_ends_with($state, 'FAILED')) {
            return BatchStatus::Failed;
        }

        if (str_ends_with($state, 'CANCELLED')) {
            return BatchStatus::Cancelled;
        }

        if (str_ends_with($state, 'EXPIRED')) {
            return BatchStatus::Expired;
        }

        if (str_ends_with($state, 'PENDING')) {
            return BatchStatus::Validating;
        }

        return BatchStatus::InProgress;
    }
}
