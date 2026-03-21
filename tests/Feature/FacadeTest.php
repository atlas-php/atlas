<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;

it('resolves providers() through the facade', function () {
    expect(Atlas::providers())->toBeInstanceOf(ProviderRegistryContract::class);
});

it('has AtlasManager as the facade root', function () {
    expect(Atlas::getFacadeRoot())->toBeInstanceOf(AtlasManager::class);
});
