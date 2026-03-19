<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Facades\Atlas;

it('resolves providers() through the facade', function () {
    expect(Atlas::providers())->toBeInstanceOf(ProviderRegistryContract::class);
});

it('has AtlasManager as the facade root', function () {
    expect(Atlas::getFacadeRoot())->toBeInstanceOf(AtlasManager::class);
});
