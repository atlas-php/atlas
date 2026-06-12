<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\BatchHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;

/**
 * A batch handler stub that records calls and returns canned responses.
 */
function fakeBatchHandler(): BatchHandler
{
    return new class implements BatchHandler
    {
        public function submit(Batch $batch): BatchResponse
        {
            return new BatchResponse('batch_'.$batch->modality->value, BatchStatus::Validating, new RequestCounts(total: $batch->count()));
        }

        public function status(string $batchId): BatchResponse
        {
            return new BatchResponse($batchId, BatchStatus::Completed, new RequestCounts(total: 1, succeeded: 1));
        }

        public function results(string $batchId): iterable
        {
            yield new BatchResult('a', BatchResultStatus::Succeeded);
        }

        public function cancel(string $batchId): BatchResponse
        {
            return new BatchResponse($batchId, BatchStatus::Cancelling, new RequestCounts);
        }
    };
}

function batchDriverWith(ProviderCapabilities $caps): Driver
{
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');
    $http = Mockery::mock(HttpClient::class);

    return new class($config, $http, $caps) extends Driver
    {
        public function __construct($config, $http, private ProviderCapabilities $declared)
        {
            parent::__construct($config, $http);
        }

        public function capabilities(): ProviderCapabilities
        {
            return $this->declared;
        }

        public function name(): string
        {
            return 'test';
        }
    };
}

it('rejects a modality the provider cannot batch', function () {
    $caps = new ProviderCapabilities(batch: true, batchModalities: ['text']);
    $batch = new Batch('test', Modality::Embed, []);

    batchDriverWith($caps)->batch($batch);
})->throws(UnsupportedFeatureException::class, 'batch:embed');

it('rejects batch entirely when the provider has no batch handler', function () {
    $caps = new ProviderCapabilities(batch: true, batchModalities: ['embed']);
    $batch = new Batch('test', Modality::Embed, []);

    // capability allows it, but the default driver has no batchHandler()
    batchDriverWith($caps)->batch($batch);
})->throws(UnsupportedFeatureException::class, 'batch');

it('delegates submit/status/results/cancel to the batch handler', function () {
    $caps = new ProviderCapabilities(batch: true, batchModalities: ['embed']);
    $driver = batchDriverWith($caps)->withHandler('batch', fakeBatchHandler());

    $batch = new Batch('test', Modality::Embed, [
        new BatchLine('a', new EmbedRequest('m', 'x')),
    ]);

    $submit = $driver->batch($batch);
    expect($submit->batchId)->toBe('batch_embed');
    expect($submit->status)->toBe(BatchStatus::Validating);
    expect($submit->counts->total)->toBe(1);

    $status = $driver->batchStatus('batch_embed');
    expect($status->status)->toBe(BatchStatus::Completed);

    $results = iterator_to_array($driver->batchResults('batch_embed'));
    expect($results)->toHaveCount(1);
    expect($results[0]->customId)->toBe('a');

    $cancel = $driver->batchCancel('batch_embed');
    expect($cancel->status)->toBe(BatchStatus::Cancelling);
});
