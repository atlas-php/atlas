<?php

declare(strict_types=1);

use Atlasphp\Atlas\Models\Services\ListModelsService;
use Illuminate\Cache\CacheManager;
use Illuminate\Http\Client\Factory;

test('ListModelsService is registered as singleton', function (): void {
    $instance1 = app(ListModelsService::class);
    $instance2 = app(ListModelsService::class);

    expect($instance1)->toBeInstanceOf(ListModelsService::class)
        ->and($instance1)->toBe($instance2);
});

test('ListModelsService uses configured cache store', function (): void {
    config()->set('atlas.models.cache.store', 'array');

    // Re-resolve to pick up config change
    $this->app->forgetInstance(ListModelsService::class);
    $this->app->singleton(ListModelsService::class, function ($app) {
        /** @var CacheManager $cacheManager */
        $cacheManager = $app->make('cache');
        $store = config('atlas.models.cache.store');

        return new ListModelsService(
            $app->make(Factory::class),
            $cacheManager->store($store),
        );
    });

    $service = app(ListModelsService::class);

    expect($service)->toBeInstanceOf(ListModelsService::class);
});
