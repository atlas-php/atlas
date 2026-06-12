<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Handlers;

use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;

/**
 * Handler for submitting and managing provider batch jobs.
 *
 * Submission serializes each line using the provider's existing modality
 * payload builder; result retrieval parses each line using the provider's
 * existing response parser. Implementations own only the provider's batch
 * transport (file upload vs inline, status mapping, result streaming).
 */
interface BatchHandler
{
    /**
     * Submit a batch job and return its initial state.
     */
    public function submit(Batch $batch): BatchResponse;

    /**
     * Fetch the current state of a previously submitted batch.
     */
    public function status(string $batchId): BatchResponse;

    /**
     * Stream the per-line results of a completed batch.
     *
     * Implementations MUST perform the underlying fetch eagerly (so transport
     * failures surface to the driver's exception translation) and yield parsed
     * results. Per-line errors are captured on {@see BatchResult::$error}, not
     * thrown.
     *
     * @return iterable<int, BatchResult>
     */
    public function results(string $batchId): iterable;

    /**
     * Request cancellation of an in-flight batch.
     */
    public function cancel(string $batchId): BatchResponse;
}
