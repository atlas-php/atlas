<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Pending\ProviderRequest;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\VoiceList;

function createProviderPending(?Driver $driver = null): ProviderRequest
{
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $registry->shouldReceive('resolve')->with('openai')->andReturn($driver);

    return new ProviderRequest('openai', $registry);
}

it('delegates models to driver', function () {
    $list = new ModelList(['gpt-4o', 'gpt-3.5']);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('models')->once()->andReturn($list);

    expect(createProviderPending($driver)->models())->toBe($list);
});

it('delegates voices to driver', function () {
    $list = new VoiceList(['alloy', 'echo']);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('voices')->once()->andReturn($list);

    expect(createProviderPending($driver)->voices())->toBe($list);
});

it('delegates validate to driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('validate')->once()->andReturn(true);

    expect(createProviderPending($driver)->validate())->toBeTrue();
});

it('delegates capabilities to driver', function () {
    $caps = new ProviderCapabilities(text: true, stream: true);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->once()->andReturn($caps);

    expect(createProviderPending($driver)->capabilities())->toBe($caps);
});

it('delegates name to driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('name')->once()->andReturn('OpenAI');

    expect(createProviderPending($driver)->name())->toBe('OpenAI');
});
