<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\BatchResponse;

function openAiDriverWith(HttpClient $http): OpenAiDriver
{
    return new OpenAiDriver(
        new ProviderConfig(provider: 'openai', apiKey: 'test', baseUrl: 'https://api.openai.com/v1'),
        $http,
    );
}

it('resolves the batch handler and submits through the driver', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('postMultipart')->once()->andReturn(['id' => 'file_x']);
    $http->shouldReceive('post')->once()
        ->andReturn(['id' => 'batch_x', 'status' => 'validating', 'request_counts' => ['total' => 1, 'completed' => 0, 'failed' => 0]]);

    $batch = new Batch('openai', Modality::Embed, [
        new BatchLine('a', new EmbedRequest('text-embedding-3-small', 'x')),
    ]);

    $response = openAiDriverWith($http)->batch($batch);

    expect($response)->toBeInstanceOf(BatchResponse::class);
    expect($response->batchId)->toBe('batch_x');
});

it('delegates batchStatus through the driver-resolved handler', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->once()
        ->andReturn(['id' => 'b', 'status' => 'completed', 'request_counts' => ['total' => 1, 'completed' => 1, 'failed' => 0]]);

    expect(openAiDriverWith($http)->batchStatus('b')->batchId)->toBe('b');
});
