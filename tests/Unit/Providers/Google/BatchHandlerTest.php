<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Google\Handlers\Batch;
use Atlasphp\Atlas\Providers\Google\Handlers\Text;
use Atlasphp\Atlas\Providers\Google\MediaResolver;
use Atlasphp\Atlas\Providers\Google\MessageFactory;
use Atlasphp\Atlas\Providers\Google\ResponseParser;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch as BatchRequest;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\TextRequest;

function googleBatchHandler(HttpClient $http): Batch
{
    $config = new ProviderConfig(provider: 'google', apiKey: 'test', baseUrl: 'https://generativelanguage.googleapis.com');
    $toolMapper = new ToolMapper;
    $text = new Text($config, $http, new MessageFactory, new MediaResolver, $toolMapper, new ResponseParser($toolMapper));

    return new Batch($config, $http, $text);
}

function googleTextBatch(): BatchRequest
{
    return new BatchRequest('google', Modality::Text, [
        new BatchLine('request-1', new TextRequest('gemini-2.5-flash', null, 'Say ALPHA', [], [], null, null, null, [], [], [])),
        new BatchLine('request-2', new TextRequest('gemini-2.5-flash', null, 'Say BETA', [], [], null, null, null, [], [], [])),
    ]);
}

it('submits inline requests with the documented nesting and model in the URL', function () {
    $http = Mockery::mock(HttpClient::class);

    $captured = null;
    $capturedUrl = null;
    $http->shouldReceive('post')->once()
        ->withArgs(function (string $url, array $headers, array $body) use (&$captured, &$capturedUrl) {
            $capturedUrl = $url;
            $captured = $body;

            return $headers['x-goog-api-key'] === 'test';
        })
        ->andReturn(['name' => 'batches/123', 'metadata' => ['state' => 'JOB_STATE_PENDING'], 'done' => false]);

    $response = googleBatchHandler($http)->submit(googleTextBatch());

    expect($capturedUrl)->toBe('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:batchGenerateContent');
    expect($response->batchId)->toBe('batches/123');
    expect($response->status)->toBe(BatchStatus::Validating);

    // batch.input_config.requests.requests[] with metadata.key per line.
    $requests = $captured['batch']['input_config']['requests']['requests'];
    expect($requests)->toHaveCount(2);
    expect($requests[0]['metadata']['key'])->toBe('request-1');
    expect($requests[0]['request'])->toHaveKey('contents');
});

it('maps job state by suffix, tolerating JOB_STATE_* and BATCH_STATE_*', function (string $state, BatchStatus $expected) {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['name' => 'batches/1', 'metadata' => ['state' => $state]]);

    expect(googleBatchHandler($http)->status('batches/1')->status)->toBe($expected);
})->with([
    ['JOB_STATE_PENDING', BatchStatus::Validating],
    ['JOB_STATE_RUNNING', BatchStatus::InProgress],
    ['JOB_STATE_SUCCEEDED', BatchStatus::Completed],
    ['JOB_STATE_FAILED', BatchStatus::Failed],
    ['JOB_STATE_CANCELLED', BatchStatus::Cancelled],
    ['JOB_STATE_EXPIRED', BatchStatus::Expired],
    ['BATCH_STATE_RUNNING', BatchStatus::InProgress],   // leaked spelling, still handled
    ['BATCH_STATE_SUCCEEDED', BatchStatus::Completed],
]);

it('correlates results by metadata.key, not array order', function () {
    $http = Mockery::mock(HttpClient::class);
    // Note: returned OUT OF ORDER (request-2 first) to prove key-based join.
    $http->shouldReceive('get')->once()->andReturn([
        'name' => 'batches/1',
        'metadata' => ['state' => 'JOB_STATE_SUCCEEDED'],
        'response' => ['inlinedResponses' => ['inlinedResponses' => [
            ['metadata' => ['key' => 'request-2'], 'response' => ['candidates' => [['content' => ['parts' => [['text' => 'BETA']]]]], 'usageMetadata' => ['promptTokenCount' => 4, 'candidatesTokenCount' => 1]]],
            ['metadata' => ['key' => 'request-1'], 'error' => ['code' => 3, 'message' => 'bad request']],
        ]]],
    ]);

    $results = iterator_to_array(googleBatchHandler($http)->results('batches/1'));

    expect($results)->toHaveCount(2);
    expect($results[0]->customId)->toBe('request-2');
    expect($results[0]->status)->toBe(BatchResultStatus::Succeeded);
    expect(trim($results[0]->response->text))->toBe('BETA');
    expect($results[1]->customId)->toBe('request-1');
    expect($results[1]->status)->toBe(BatchResultStatus::Errored);
    expect($results[1]->error->getMessage())->toBe('bad request');
});

it('returns no results while still running', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['name' => 'batches/1', 'metadata' => ['state' => 'JOB_STATE_RUNNING']]);

    expect(iterator_to_array(googleBatchHandler($http)->results('batches/1')))->toBe([]);
});

it('cancels a batch', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url) => str_ends_with($url, '/v1beta/batches/1:cancel'))
        ->andReturn(['name' => 'batches/1', 'metadata' => ['state' => 'JOB_STATE_CANCELLED']]);

    expect(googleBatchHandler($http)->cancel('batches/1')->status)->toBe(BatchStatus::Cancelled);
});

it('falls back to a status fetch when cancel returns an empty body', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()->andReturn([]); // empty cancel ack
    $http->shouldReceive('get')->once()->andReturn(['name' => 'batches/1', 'metadata' => ['state' => 'JOB_STATE_CANCELLED']]);

    expect(googleBatchHandler($http)->cancel('batches/1')->status)->toBe(BatchStatus::Cancelled);
});

it('surfaces a top-level operation error', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn([
        'name' => 'batches/1',
        'metadata' => ['state' => 'JOB_STATE_FAILED'],
        'error' => ['code' => 13, 'message' => 'internal failure'],
    ]);

    $response = googleBatchHandler($http)->status('batches/1');

    expect($response->status)->toBe(BatchStatus::Failed);
    expect($response->error)->toBe('internal failure');
});

it('counts succeeded and failed inline responses', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn([
        'name' => 'batches/1',
        'metadata' => ['state' => 'JOB_STATE_SUCCEEDED'],
        'response' => ['inlinedResponses' => ['inlinedResponses' => [
            ['metadata' => ['key' => 'a'], 'response' => ['candidates' => []]],
            ['metadata' => ['key' => 'b'], 'error' => ['message' => 'x']],
        ]]],
    ]);

    $counts = googleBatchHandler($http)->status('batches/1')->counts;

    expect($counts->total)->toBe(2);
    expect($counts->succeeded)->toBe(1);
    expect($counts->failed)->toBe(1);
});
