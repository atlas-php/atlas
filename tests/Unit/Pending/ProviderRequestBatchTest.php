<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Pending\BatchRequest;
use Atlasphp\Atlas\Pending\ProviderRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;
use Atlasphp\Atlas\Responses\RequestCounts;

function providerRequestWith(Driver $driver): ProviderRequest
{
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new ProviderRequest('openai', $registry);
}

// ─── ProviderRequest batch delegation ────────────────────────────────────────

it('delegates batchStatus to the driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchStatus')->once()->with('b')
        ->andReturn(new BatchResponse('b', BatchStatus::Completed, new RequestCounts));

    expect(providerRequestWith($driver)->batchStatus('b')->status)->toBe(BatchStatus::Completed);
});

it('delegates batchResults to the driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchResults')->once()->with('b')
        ->andReturn([new BatchResult('a', BatchResultStatus::Succeeded)]);

    $results = iterator_to_array(providerRequestWith($driver)->batchResults('b'));

    expect($results)->toHaveCount(1);
    expect($results[0]->customId)->toBe('a');
});

it('delegates batchCancel to the driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('batchCancel')->once()->with('b')
        ->andReturn(new BatchResponse('b', BatchStatus::Cancelling, new RequestCounts));

    expect(providerRequestWith($driver)->batchCancel('b')->status)->toBe(BatchStatus::Cancelling);
});

// ─── Pending builders' Batchable methods ─────────────────────────────────────

it('exposes batchModality and batchProvider on the text builder', function () {
    $text = Atlas::text('openai', 'gpt-5');

    expect($text->batchModality())->toBe(Modality::Text);
    expect($text->batchProvider())->toBe('openai');
});

it('exposes batchModality and batchProvider on the embed builder', function () {
    $embed = Atlas::embed('openai', 'text-embedding-3-small');

    expect($embed->batchModality())->toBe(Modality::Embed);
    expect($embed->batchProvider())->toBe('openai');
});

it('adds a real pending text builder to a batch (exercises batchModality/Provider/buildRequest)', function () {
    $batch = Atlas::batch('openai')->add(Atlas::text('openai', 'gpt-5')->message('hi'), key: 'k');

    expect($batch->count())->toBe(1);
});

// ─── AtlasManager batch()/batchGroup() (persistence disabled) ────────────────

it('Atlas::batch() returns a stateless builder when persistence is off', function () {
    expect(Atlas::batch('openai'))->toBeInstanceOf(BatchRequest::class);
});

it('Atlas::batchGroup() requires persistence', function () {
    Atlas::batchGroup('x');
})->throws(BatchException::class, 'requires atlas persistence');
