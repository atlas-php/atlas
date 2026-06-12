<?php

declare(strict_types=1);

use Atlasphp\Atlas\Batch\BatchService;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Pending\BatchRequest;
use Atlasphp\Atlas\Pending\Contracts\Batchable;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Requests\Batch;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\RequestCounts;

/**
 * A stub pending request that reports a fixed modality/provider and DTO.
 */
function batchable(Modality $modality, string $provider, object $dto): Batchable
{
    return new class($modality, $provider, $dto) implements Batchable
    {
        public function __construct(private Modality $modality, private string $provider, private object $dto) {}

        public function batchModality(): Modality
        {
            return $this->modality;
        }

        public function batchProvider(): string
        {
            return $this->provider;
        }

        public function buildRequest(): object
        {
            return $this->dto;
        }
    };
}

function cleanText(): TextRequest
{
    return new TextRequest('gpt-5', null, 'Caption this.', [], [], null, null, null, [], [], []);
}

function builder(?ProviderRegistryContract $registry = null): BatchRequest
{
    return new BatchRequest('openai', $registry ?? Mockery::mock(ProviderRegistryContract::class));
}

it('accepts a clean text request and tracks the line', function () {
    $b = builder()->add(batchable(Modality::Text, 'openai', cleanText()), 'img-1');

    expect($b->count())->toBe(1);
});

it('rejects a non-batchable request type', function () {
    builder()->add(new stdClass, 'k');
})->throws(BatchException::class, 'cannot be batched');

it('rejects a request carrying local tools', function () {
    $withTools = new TextRequest('gpt-5', null, 'hi', [], [], null, null, null, ['a_tool'], [], []);

    builder()->add(batchable(Modality::Text, 'openai', $withTools), 'k');
})->throws(BatchException::class, 'Tools cannot be used with batch');

it('rejects a request carrying provider tools', function () {
    $withProviderTools = new TextRequest('gpt-5', null, 'hi', [], [], null, null, null, [], ['web_search'], []);

    builder()->add(batchable(Modality::Text, 'openai', $withProviderTools), 'k');
})->throws(BatchException::class, 'Tools cannot be used with batch');

it('rejects a request carrying per-request middleware', function () {
    $withMiddleware = new TextRequest('gpt-5', null, 'hi', [], [], null, null, null, [], [], [], middleware: ['some_mw']);

    builder()->add(batchable(Modality::Text, 'openai', $withMiddleware), 'k');
})->throws(BatchException::class, 'middleware does not run for batched requests');

it('rejects mixing two modalities in one batch', function () {
    builder()
        ->add(batchable(Modality::Text, 'openai', cleanText()), 'a')
        ->add(batchable(Modality::Embed, 'openai', new EmbedRequest('m', 'x')), 'b');
})->throws(BatchException::class, 'must share one modality');

it('rejects mixing two providers in one batch', function () {
    builder()
        ->add(batchable(Modality::Text, 'openai', cleanText()), 'a')
        ->add(batchable(Modality::Text, 'anthropic', cleanText()), 'b');
})->throws(BatchException::class, 'must target one provider');

it('rejects submitting an empty batch', function () {
    builder()->submit();
})->throws(BatchException::class, 'empty batch');

it('rejects group() when persistence is not enabled', function () {
    builder()->group(new BatchGroup);
})->throws(BatchException::class, 'requires atlas persistence');

it('addMany adds each request via the factory', function () {
    $items = ['x', 'y', 'z'];

    $b = builder()->addMany($items, fn (string $s) => [batchable(Modality::Embed, 'openai', new EmbedRequest('m', $s)), $s]);

    expect($b->count())->toBe(3);
});

it('submits statelessly through the resolved driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batch')
        ->once()
        ->withArgs(fn (Batch $batch) => $batch->provider === 'openai'
            && $batch->modality === Modality::Embed
            && $batch->count() === 2)
        ->andReturn(new BatchResponse('batch_x', BatchStatus::Validating, new RequestCounts(total: 2)));

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    $response = builder($registry)
        ->add(batchable(Modality::Embed, 'openai', new EmbedRequest('m', 'a')), 'a')
        ->add(batchable(Modality::Embed, 'openai', new EmbedRequest('m', 'b')), 'b')
        ->submit();

    expect($response)->toBeInstanceOf(BatchResponse::class);
    expect($response->batchId)->toBe('batch_x');
});

it('tracks via the batch service and passes the group through', function () {
    $job = new BatchJob;
    $group = new BatchGroup;

    $service = Mockery::mock(BatchService::class);
    $service->shouldReceive('submitAndTrack')->once()
        ->withArgs(fn (Batch $batch, $g) => $batch->modality === Modality::Text && $g === $group)
        ->andReturn($job);

    $builder = new BatchRequest('openai', Mockery::mock(ProviderRegistryContract::class), $service);

    $result = $builder
        ->group($group)
        ->add(batchable(Modality::Text, 'openai', cleanText()), 'a')
        ->submit();

    expect($result)->toBe($job);
});

it('group() succeeds when persistence is available', function () {
    $service = Mockery::mock(BatchService::class);
    $builder = new BatchRequest('openai', Mockery::mock(ProviderRegistryContract::class), $service);

    expect($builder->group(new BatchGroup))->toBe($builder);
});

it('applies a custom completion window', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batch')
        ->once()
        ->withArgs(fn (Batch $batch) => $batch->completionWindow === '12h')
        ->andReturn(new BatchResponse('b', BatchStatus::Validating, new RequestCounts));

    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    builder($registry)
        ->completionWindow('12h')
        ->add(batchable(Modality::Text, 'openai', cleanText()), 'a')
        ->submit();
});
