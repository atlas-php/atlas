<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\BatchResponse;

function googleDriverWith(HttpClient $http): GoogleDriver
{
    return new GoogleDriver(
        new ProviderConfig(provider: 'google', apiKey: 'test', baseUrl: 'https://generativelanguage.googleapis.com'),
        $http,
    );
}

it('resolves the batch handler and submits through the driver', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->andReturn(['name' => 'batches/1', 'metadata' => ['state' => 'JOB_STATE_PENDING']]);

    $batch = new Batch('google', Modality::Text, [
        new BatchLine('a', new TextRequest('gemini-2.5-flash', null, 'hi', [], [], null, null, null, [], [], [])),
    ]);

    $response = googleDriverWith($http)->batch($batch);

    expect($response)->toBeInstanceOf(BatchResponse::class);
    expect($response->batchId)->toBe('batches/1');
});

it('still routes the synchronous text handler after the batch wiring extraction', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()->andReturn([
        'candidates' => [['content' => ['parts' => [['text' => 'hello']]]]],
        'usageMetadata' => ['promptTokenCount' => 3, 'candidatesTokenCount' => 1],
    ]);

    $response = googleDriverWith($http)->text(new TextRequest('gemini-2.5-flash', null, 'hi', [], [], null, null, null, [], [], []));

    expect(trim($response->text))->toBe('hello');
});
