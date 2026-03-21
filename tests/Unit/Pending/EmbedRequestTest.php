<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\Usage;

function createEmbedPending(?Driver $driver = null): EmbedRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new EmbedRequest('openai', 'text-embedding-3-small', $registry);
}

it('returns $this from fluent methods', function () {
    $pending = createEmbedPending();

    expect($pending->fromInput('hello'))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('dispatches asEmbeddings to driver', function () {
    $response = new EmbeddingsResponse([[0.1, 0.2, 0.3]], new Usage(5, 0));
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(embed: true));
    $driver->shouldReceive('embed')->once()->andReturn($response);

    $result = createEmbedPending($driver)->fromInput('hello world')->asEmbeddings();

    expect($result)->toBe($response);
});

it('throws when embed capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities);
    $driver->shouldReceive('name')->andReturn('test');

    createEmbedPending($driver)->fromInput('test')->asEmbeddings();
})->throws(UnsupportedFeatureException::class);

it('throws when input is not set', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(embed: true));

    createEmbedPending($driver)->asEmbeddings();
})->throws(InvalidArgumentException::class, 'Input must be provided');

it('builds request with correct values', function () {
    $request = createEmbedPending()
        ->fromInput('hello world')
        ->withProviderOptions(['dim' => 256])
        ->buildRequest();

    expect($request->model)->toBe('text-embedding-3-small');
    expect($request->input)->toBe('hello world');
    expect($request->providerOptions)->toBe(['dim' => 256]);
});
