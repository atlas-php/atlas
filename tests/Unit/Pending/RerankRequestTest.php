<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Pending\RerankRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;
use Atlasphp\Atlas\Testing\RerankResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function createRerankPending(?Driver $driver = null): RerankRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('cohere')->andReturn($driver);

    return new RerankRequest('cohere', 'rerank-v3.5', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createRerankPending();

    expect($pending->query('test'))->toBe($pending);
    expect($pending->documents(['doc1']))->toBe($pending);
    expect($pending->topN(5))->toBe($pending);
    expect($pending->maxTokensPerDoc(512))->toBe($pending);
    expect($pending->minScore(0.5))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asReranked to driver', function () {
    $response = new RerankResponse([
        new RerankResult(0, 0.95, 'doc1'),
        new RerankResult(1, 0.80, 'doc2'),
    ]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));
    $driver->shouldReceive('rerank')->once()->andReturn($response);

    $result = createRerankPending($driver)
        ->query('test query')
        ->documents(['doc1', 'doc2'])
        ->asReranked();

    expect($result)->toBe($response);
});

it('throws when rerank capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createRerankPending($driver)
        ->query('test')
        ->documents(['doc1'])
        ->asReranked();
})->throws(UnsupportedFeatureException::class);

it('throws when query is not set', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));

    createRerankPending($driver)
        ->documents(['doc1'])
        ->asReranked();
})->throws(InvalidArgumentException::class, 'Query must be provided');

it('throws when documents are not set', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));

    createRerankPending($driver)
        ->query('test')
        ->asReranked();
})->throws(InvalidArgumentException::class, 'Documents must be provided');

it('throws when documents are empty', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));

    createRerankPending($driver)
        ->query('test')
        ->documents([])
        ->asReranked();
})->throws(InvalidArgumentException::class, 'Documents must be provided');

it('applies minScore filter client-side', function () {
    $response = new RerankResponse([
        new RerankResult(0, 0.95, 'doc1'),
        new RerankResult(1, 0.50, 'doc2'),
        new RerankResult(2, 0.20, 'doc3'),
    ]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));
    $driver->shouldReceive('rerank')->once()->andReturn($response);

    $result = createRerankPending($driver)
        ->query('test')
        ->documents(['doc1', 'doc2', 'doc3'])
        ->minScore(0.40)
        ->asReranked();

    expect($result->results)->toHaveCount(2);
    expect($result->results[0]->score)->toBe(0.95);
    expect($result->results[1]->score)->toBe(0.50);
});

it('builds request with correct values', function () {
    $request = createRerankPending()
        ->query('test query')
        ->documents(['doc1', 'doc2'])
        ->topN(3)
        ->maxTokensPerDoc(512)
        ->withProviderOptions(['return_documents' => true])
        ->buildRequest();

    expect($request->model)->toBe('rerank-v3.5');
    expect($request->query)->toBe('test query');
    expect($request->documents)->toBe(['doc1', 'doc2']);
    expect($request->topN)->toBe(3);
    expect($request->maxTokensPerDoc)->toBe(512);
    expect($request->providerOptions)->toBe(['return_documents' => true]);
});

it('queued asReranked returns PendingExecution', function () {
    Queue::fake();

    $result = createRerankPending()
        ->query('test')
        ->documents(['doc1'])
        ->queue()
        ->asReranked();

    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('fires ModalityCompleted on error', function () {
    Event::fake();

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));
    $driver->shouldReceive('rerank')->andThrow(new RuntimeException('fail'));

    try {
        createRerankPending($driver)->query('q')->documents(['d'])->asReranked();
    } catch (RuntimeException) {
    }

    Event::assertDispatched(
        ModalityCompleted::class,
        fn ($e) => $e->modality === Modality::Rerank && $e->usage === null
    );
});

it('executeFromPayload rebuilds and executes', function () {
    Atlas::fake([
        RerankResponseFake::make(),
    ]);

    $result = RerankRequest::executeFromPayload(
        payload: ['provider' => 'openai', 'model' => 'rerank-v3.5', 'query' => 'test', 'documents' => ['d1'], 'topN' => null, 'maxTokensPerDoc' => null, 'minScore' => null, 'providerOptions' => [], 'meta' => [], 'variables' => [], 'interpolate_messages' => false],
        terminal: 'asReranked',
    );

    expect($result)->toBeInstanceOf(RerankResponse::class);
});

it('fires ModalityCompleted after minScore filtering', function () {
    Event::fake();

    $response = new RerankResponse([
        new RerankResult(0, 0.95, 'doc1'),
        new RerankResult(1, 0.50, 'doc2'),
        new RerankResult(2, 0.20, 'doc3'),
    ]);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(rerank: true));
    $driver->shouldReceive('rerank')->once()->andReturn($response);

    createRerankPending($driver)
        ->query('test')
        ->documents(['doc1', 'doc2', 'doc3'])
        ->minScore(0.40)
        ->asReranked();

    // Event should fire AFTER filtering — verify it was dispatched
    Event::assertDispatched(ModalityCompleted::class, function (ModalityCompleted $event) {
        return $event->modality === Modality::Rerank;
    });
});
