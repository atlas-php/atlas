<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Batch;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Embed;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Text;
use Atlasphp\Atlas\Providers\OpenAi\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\MessageFactory;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch as BatchRequest;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\EmbedRequest;

function openAiBatchHandler(HttpClient $http): Batch
{
    $config = new ProviderConfig(provider: 'openai', apiKey: 'test', baseUrl: 'https://api.openai.com/v1');
    $toolMapper = new ToolMapper;

    $text = new Text($config, $http, new MessageFactory, new MediaResolver, $toolMapper, new ResponseParser($toolMapper));
    $embed = new Embed($config, $http);

    return new Batch($config, $http, $text, $embed);
}

function embedBatch(): BatchRequest
{
    return new BatchRequest('openai', Modality::Embed, [
        new BatchLine('chunk-1', new EmbedRequest('text-embedding-3-small', 'hello')),
        new BatchLine('chunk-2', new EmbedRequest('text-embedding-3-small', 'world')),
    ]);
}

it('uploads JSONL and creates a batch, serializing each line via the embed body builder', function () {
    $http = Mockery::mock(HttpClient::class);

    $capturedJsonl = null;
    $http->shouldReceive('postMultipart')->once()
        ->withArgs(function (string $url, array $headers, array $data, array $attachments) use (&$capturedJsonl) {
            $capturedJsonl = $attachments[0]['contents'];

            return str_ends_with($url, '/files')
                && $data['purpose'] === 'batch'
                && ! isset($headers['Content-Type']);
        })
        ->andReturn(['id' => 'file_abc']);

    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url, array $h, array $body) => str_ends_with($url, '/batches')
            && $body['input_file_id'] === 'file_abc'
            && $body['endpoint'] === '/v1/embeddings'
            && $body['completion_window'] === '24h')
        ->andReturn(['id' => 'batch_xyz', 'status' => 'validating', 'request_counts' => ['total' => 2, 'completed' => 0, 'failed' => 0], 'input_file_id' => 'file_abc']);

    $response = openAiBatchHandler($http)->submit(embedBatch());

    expect($response->batchId)->toBe('batch_xyz');
    expect($response->status)->toBe(BatchStatus::Validating);
    expect($response->counts->total)->toBe(2);
    expect($response->counts->processing)->toBe(2);

    // Each JSONL line matches the synchronous embed body builder exactly.
    $lines = explode("\n", $capturedJsonl);
    expect($lines)->toHaveCount(2);
    $first = json_decode($lines[0], true);
    expect($first['custom_id'])->toBe('chunk-1');
    expect($first['url'])->toBe('/v1/embeddings');
    expect($first['body']['model'])->toBe('text-embedding-3-small');
    expect($first['body']['input'])->toBe('hello');
});

it('maps each provider status to the normalized enum', function (string $raw, BatchStatus $expected) {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'status' => $raw, 'request_counts' => ['total' => 1, 'completed' => 1, 'failed' => 0]]);

    expect(openAiBatchHandler($http)->status('b')->status)->toBe($expected);
})->with([
    ['validating', BatchStatus::Validating],
    ['in_progress', BatchStatus::InProgress],
    ['finalizing', BatchStatus::Finalizing],
    ['completed', BatchStatus::Completed],
    ['failed', BatchStatus::Failed],
    ['expired', BatchStatus::Expired],
    ['cancelling', BatchStatus::Cancelling],
    ['cancelled', BatchStatus::Cancelled],
]);

it('returns no results while the output file is not ready', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'status' => 'in_progress', 'output_file_id' => null]);

    expect(iterator_to_array(openAiBatchHandler($http)->results('b')))->toBe([]);
});

it('parses completed embedding results via the embed parser', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn([
        'id' => 'b', 'status' => 'completed', 'endpoint' => '/v1/embeddings', 'output_file_id' => 'file_out',
    ]);

    $jsonl = implode("\n", [
        json_encode(['custom_id' => 'chunk-1', 'response' => ['status_code' => 200, 'body' => ['data' => [['index' => 0, 'embedding' => [0.1, 0.2]]], 'usage' => ['prompt_tokens' => 3]]]]),
        json_encode(['custom_id' => 'chunk-2', 'error' => ['message' => 'bad input']]),
    ]);
    $http->shouldReceive('getRaw')->once()->andReturn($jsonl);

    $results = iterator_to_array(openAiBatchHandler($http)->results('b'));

    expect($results)->toHaveCount(2);
    expect($results[0]->customId)->toBe('chunk-1');
    expect($results[0]->status)->toBe(BatchResultStatus::Succeeded);
    expect($results[0]->response->embeddings[0])->toBe([0.1, 0.2]);
    expect($results[1]->customId)->toBe('chunk-2');
    expect($results[1]->status)->toBe(BatchResultStatus::Errored);
    expect($results[1]->error->getMessage())->toBe('bad input');
});

it('throws when the input-file upload returns no id', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('postMultipart')->once()->andReturn(['object' => 'file']); // no 'id'

    openAiBatchHandler($http)->submit(embedBatch());
})->throws(BatchException::class, 'no file id');

it('marks a line with a non-2xx status_code as errored', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn(['id' => 'b', 'status' => 'completed', 'endpoint' => '/v1/responses', 'output_file_id' => 'file_out']);
    $http->shouldReceive('getRaw')->once()->andReturn(json_encode([
        'custom_id' => 'x',
        'response' => ['status_code' => 400, 'body' => ['error' => ['message' => 'context too long']]],
        'error' => null,
    ]));

    $results = iterator_to_array(openAiBatchHandler($http)->results('b'));

    expect($results[0]->status)->toBe(BatchResultStatus::Errored);
    expect($results[0]->error->getMessage())->toBe('context too long');
});

it('surfaces a job-level error from the batch object', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()->andReturn([
        'id' => 'b', 'status' => 'failed',
        'request_counts' => ['total' => 1, 'completed' => 0, 'failed' => 1],
        'errors' => ['data' => [['code' => 'invalid', 'message' => 'input file malformed']]],
    ]);

    $response = openAiBatchHandler($http)->status('b');

    expect($response->status)->toBe(BatchStatus::Failed);
    expect($response->error)->toBe('input file malformed');
});

it('cancels a batch', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->withArgs(fn (string $url) => str_ends_with($url, '/batches/b/cancel'))
        ->andReturn(['id' => 'b', 'status' => 'cancelling', 'request_counts' => ['total' => 5, 'completed' => 2, 'failed' => 0]]);

    expect(openAiBatchHandler($http)->cancel('b')->status)->toBe(BatchStatus::Cancelling);
});
