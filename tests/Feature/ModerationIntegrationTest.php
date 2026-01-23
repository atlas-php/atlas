<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Providers\Facades\Atlas;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\ModerationService;
use Atlasphp\Atlas\Providers\Support\PendingModerationRequest;

test('facade provides moderation method', function () {
    $request = Atlas::moderation();

    expect($request)->toBeInstanceOf(PendingModerationRequest::class);
});

test('facade moderation method accepts provider parameter', function () {
    $request = Atlas::moderation('openai');

    expect($request)->toBeInstanceOf(PendingModerationRequest::class);
});

test('facade moderation method accepts provider and model parameters', function () {
    $request = Atlas::moderation('openai', 'text-moderation-latest');

    expect($request)->toBeInstanceOf(PendingModerationRequest::class);
});

test('ModerationService is registered as singleton', function () {
    $service1 = app(ModerationService::class);
    $service2 = app(ModerationService::class);

    expect($service1)->toBe($service2);
});

test('AtlasManager includes ModerationService dependency', function () {
    $manager = app(AtlasManager::class);

    expect($manager->moderation())->toBeInstanceOf(PendingModerationRequest::class);
});

test('moderation pipelines are defined', function () {
    $registry = app(PipelineRegistry::class);
    $definitions = $registry->definitions();

    expect(array_key_exists('moderation.before_moderate', $definitions))->toBeTrue();
    expect(array_key_exists('moderation.after_moderate', $definitions))->toBeTrue();
    expect(array_key_exists('moderation.on_error', $definitions))->toBeTrue();
});
