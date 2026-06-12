<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Anthropic\AnthropicDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\BatchLine;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\BatchResponse;

it('resolves the batch handler and submits through the driver', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()
        ->andReturn(['id' => 'msgbatch_1', 'processing_status' => 'in_progress', 'request_counts' => ['processing' => 1, 'succeeded' => 0, 'errored' => 0]]);

    $driver = new AnthropicDriver(
        new ProviderConfig(provider: 'anthropic', apiKey: 'test', baseUrl: 'https://api.anthropic.com/v1'),
        $http,
    );

    $batch = new Batch('anthropic', Modality::Text, [
        new BatchLine('a', new TextRequest('claude-sonnet-4-20250514', null, 'hi', [], [], 64, null, null, [], [], [])),
    ]);

    $response = $driver->batch($batch);

    expect($response)->toBeInstanceOf(BatchResponse::class);
    expect($response->batchId)->toBe('msgbatch_1');
});

it('still routes the synchronous text handler after the batch wiring extraction', function () {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('post')->once()->andReturn([
        'id' => 'msg_1',
        'content' => [['type' => 'text', 'text' => 'hello']],
        'usage' => ['input_tokens' => 3, 'output_tokens' => 1],
        'stop_reason' => 'end_turn',
    ]);

    $driver = new AnthropicDriver(
        new ProviderConfig(provider: 'anthropic', apiKey: 'test', baseUrl: 'https://api.anthropic.com/v1'),
        $http,
    );

    $response = $driver->text(new TextRequest('claude-sonnet-4-20250514', null, 'hi', [], [], 64, null, null, [], [], []));

    expect(trim($response->text))->toBe('hello');
});
