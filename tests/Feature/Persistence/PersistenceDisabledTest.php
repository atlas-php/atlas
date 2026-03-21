<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Services\ExecutionService;

beforeEach(function () {
    config()->set('atlas.persistence.enabled', false);
});

it('ExecutionService operations no-op without exceptions when persistence is disabled', function () {
    // Even with persistence disabled, ExecutionService itself is stateful
    // and doesn't guard on config — the middleware layer guards.
    // But we verify that calling operations doesn't throw.
    $service = app(ExecutionService::class);

    // These should all work without error — the service is stateless
    // regarding config; it just tracks in memory + DB.
    expect($service->hasActiveExecution())->toBeFalse();
    expect($service->getExecution())->toBeNull();
    expect($service->getCurrentToolCall())->toBeNull();

    $service->markQueued();  // no-op, no execution
    $service->beginExecution(); // no-op, no execution
    $service->completeExecution(); // no-op, no execution
    $service->failExecution(new RuntimeException('test')); // no-op, no execution
    $service->linkAsset(1); // no-op, no execution
    $service->completeDirectExecution(100, 50); // no-op, no execution

    $service->reset();

    expect($service->hasActiveExecution())->toBeFalse();
});

it('ExecutionService beginStep and completeStep no-op without step', function () {
    $service = app(ExecutionService::class);

    // No step created — these should silently no-op
    $service->beginStep();
    $service->completeStep();

    expect($service->currentStep())->toBeNull();
});

it('persistence disabled config value is respected', function () {
    expect(config('atlas.persistence.enabled'))->toBeFalse();
});
