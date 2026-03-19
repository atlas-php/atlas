<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;

it('registers ProviderRegistryContract as a singleton', function () {
    $first = $this->app->make(ProviderRegistryContract::class);
    $second = $this->app->make(ProviderRegistryContract::class);

    expect($first)->toBeInstanceOf(ProviderRegistryContract::class);
    expect($first)->toBe($second);
});

it('registers AtlasManager as a singleton', function () {
    $first = $this->app->make(AtlasManager::class);
    $second = $this->app->make(AtlasManager::class);

    expect($first)->toBeInstanceOf(AtlasManager::class);
    expect($first)->toBe($second);
});

it('merges the atlas config', function () {
    expect(config('atlas.default.provider'))->not->toBeNull();
});
