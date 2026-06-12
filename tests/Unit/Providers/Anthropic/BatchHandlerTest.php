<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Anthropic\Handlers\Batch;
use Atlasphp\Atlas\Providers\Anthropic\Handlers\Text;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;
use Atlasphp\Atlas\Providers\Anthropic\MessageFactory;
use Atlasphp\Atlas\Providers\Anthropic\ResponseParser;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch as BatchRequest;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\TextRequest;

function anthropicBatchHandler(HttpClient $http): Batch
{
    $config = new ProviderConfig(provider: 'anthropic', apiKey: 'test', baseUrl: 'https://api.anthropic.com/v1');
    $toolMapper = new ToolMapper;
    $text = new Text($config, $http, new MessageFactory, new MediaResolver, $toolMapper, new ResponseParser($toolMapper));

    return new Batch($config, $http, $text);
}

function anthropicTextBatch(): BatchRequest
{
    return new BatchRequest('anthropic', Modality::Text, [
        new BatchLine('a', new TextRequest('claude-sonnet-4-20250514', null, 'Say ALPHA', [], [], 64, null, null, [], [], [])),
    ]);
}

it('submits inline requests built from the text body builder', function () {
    $http = Mockery::mock(HttpClient::class);

    $captured = null;
    $http->shouldReceive('post')->once()
        ->withArgs(function (string $url, array $headers, array $body) use (&$captured) {
            $captured = $body;

            return str_ends_with($url, '/messages/batches')
                && $headers['x-api-key'] === 'test'
                && isset($headers['anthropic-version']);
        })
        ->andReturn(['id' => 'msgbatch_1', 'processing_status' => 'in_progress', 'request_counts' => ['processing' => 1, 'succeeded' => 0, 'errored' => 0]]);

    $response = anthropicBatchHandler($http)->submit(anthropicTextBatch());

    expect($response->batchId)->toBe('msgbatch_1');
    expect($response->status)->toBe(BatchStatus::InProgress);
    expect($captured['requests'][0]['custom_id'])->toBe('a');
    expect($captured['requests'][0]['params']['model'])->toBe('claude-sonnet-4-20250514');
    expect($captured['requests'][0]['params'])->toHaveKey('messages');
});

it('maps processing_status to the normalized enum', function (string $raw, BatchStatus $expected) {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'processing_status' => $raw, 'request_counts' => ['succeeded' => 1, 'errored' => 0, 'processing' => 0]]);

    expect(anthropicBatchHandler($http)->status('b')->status)->toBe($expected);
})->with([
    ['in_progress', BatchStatus::InProgress],
    ['canceling', BatchStatus::Cancelling],
    ['ended', BatchStatus::Completed],
]);

it('maps the lifecycle with correct terminal/successful semantics', function (string $raw, bool $terminal, bool $successful) {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'processing_status' => $raw, 'request_counts' => ['succeeded' => 1, 'errored' => 0, 'processing' => 0]]);

    $response = anthropicBatchHandler($http)->status('b');

    expect($response->isTerminal())->toBe($terminal);
    expect($response->isSuccessful())->toBe($successful);
})->with([
    // Anthropic has no "validating": a submitted batch is immediately in_progress.
    'in progress (submitted)' => ['in_progress', false, false],
    'canceling' => ['canceling', false, false],
    'ended (completed)' => ['ended', true, true],
    'unknown future' => ['some_new_state', false, false], // safe default: keep polling
]);

it('parses succeeded and errored line results', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'processing_status' => 'ended', 'results_url' => 'https://api.anthropic.com/v1/messages/batches/b/results']);

    // Blank line in the MIDDLE (a leading/trailing blank would be removed by
    // trim() before the loop, so it must be interior to hit the skip branch).
    $jsonl = implode("\n", [
        json_encode(['custom_id' => 'a', 'result' => ['type' => 'succeeded', 'message' => ['content' => [['type' => 'text', 'text' => 'ALPHA']], 'usage' => ['input_tokens' => 5, 'output_tokens' => 1], 'stop_reason' => 'end_turn']]]),
        '', // blank line → skipped
        json_encode(['custom_id' => 'b', 'result' => ['type' => 'errored', 'error' => ['type' => 'invalid_request', 'message' => 'too long']]]),
        json_encode(['custom_id' => 'c', 'result' => ['type' => 'expired']]),
        json_encode(['custom_id' => 'd', 'result' => ['type' => 'canceled']]),
    ]);
    $http->shouldReceive('getRaw')->once()->andReturn($jsonl);

    $results = iterator_to_array(anthropicBatchHandler($http)->results('b'));

    expect($results)->toHaveCount(4);
    expect($results[0]->status)->toBe(BatchResultStatus::Succeeded);
    expect(trim($results[0]->response->text))->toBe('ALPHA');
    expect($results[1]->status)->toBe(BatchResultStatus::Errored);
    expect($results[1]->error->getMessage())->toBe('too long');
    expect($results[2]->status)->toBe(BatchResultStatus::Expired);
    expect($results[3]->status)->toBe(BatchResultStatus::Cancelled);
});

it('rejects a non-text request line at submit', function () {
    $http = Mockery::mock(HttpClient::class);

    $batch = new BatchRequest('anthropic', Modality::Text, [
        new BatchLine('a', new EmbedRequest('m', 'x')),
    ]);

    anthropicBatchHandler($http)->submit($batch);
})->throws(BatchException::class, 'cannot be batched');

it('returns nothing while results_url is absent', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'processing_status' => 'in_progress']);

    expect(iterator_to_array(anthropicBatchHandler($http)->results('b')))->toBe([]);
});

it('cancels a batch', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url) => str_ends_with($url, '/messages/batches/b/cancel'))
        ->andReturn(['id' => 'b', 'processing_status' => 'canceling', 'request_counts' => ['processing' => 2]]);

    expect(anthropicBatchHandler($http)->cancel('b')->status)->toBe(BatchStatus::Cancelling);
});
