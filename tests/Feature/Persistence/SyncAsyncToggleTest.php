<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Queue\Jobs\TracksExecution;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Illuminate\Support\Facades\Queue;

// ─── Sync with persistence enabled ──────────────────────────────────

it('sync text call works with persistence enabled', function () {
    Atlas::fake();

    $response = Atlas::text('openai', 'gpt-5')
        ->message('Hello')
        ->asText();

    expect($response)->toBeInstanceOf(TextResponse::class);
});

it('sync image call works with persistence enabled', function () {
    Atlas::fake();

    $response = Atlas::image('openai', 'dall-e-3')
        ->instructions('A sunset')
        ->asImage();

    expect($response)->toBeInstanceOf(ImageResponse::class);
});

// ─── Sync with persistence disabled ─────────────────────────────────

it('sync text call works with persistence disabled', function () {
    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();
    Atlas::fake();

    $response = Atlas::text('openai', 'gpt-5')
        ->message('Hello')
        ->asText();

    expect($response)->toBeInstanceOf(TextResponse::class);
});

it('sync image call works with persistence disabled', function () {
    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();
    Atlas::fake();

    $response = Atlas::image('openai', 'dall-e-3')
        ->instructions('A sunset')
        ->asImage();

    expect($response)->toBeInstanceOf(ImageResponse::class);
});

// ─── Queue with persistence enabled ─────────────────────────────────

it('queued text creates execution record when persistence enabled', function () {
    Atlas::fake();
    Queue::fake();

    $pending = Atlas::text('openai', 'gpt-5')
        ->message('Hello')
        ->queue()
        ->asText();

    expect($pending)->toBeInstanceOf(PendingExecution::class);
    expect($pending->executionId)->not->toBeNull();

    $execution = Execution::find($pending->executionId);
    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Queued);
    expect($execution->provider)->toBe('openai');
    expect($execution->model)->toBe('gpt-5');
});

it('queued image creates execution record with correct type', function () {
    Atlas::fake();
    Queue::fake();

    $pending = Atlas::image('openai', 'dall-e-3')
        ->instructions('A sunset')
        ->queue()
        ->asImage();

    expect($pending)->toBeInstanceOf(PendingExecution::class);

    $execution = Execution::find($pending->executionId);
    expect($execution->type->value)->toBe('image');
});

// ─── Queue with persistence disabled ────────────────────────────────

it('queued text works without persistence — no execution record', function () {
    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();
    Atlas::fake();
    Queue::fake();

    $pending = Atlas::text('openai', 'gpt-5')
        ->message('Hello')
        ->queue()
        ->asText();

    expect($pending)->toBeInstanceOf(PendingExecution::class);
    expect($pending->executionId)->toBeNull();
});

it('queued image works without persistence — no execution record', function () {
    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();
    Atlas::fake();
    Queue::fake();

    $pending = Atlas::image('openai', 'dall-e-3')
        ->instructions('A sunset')
        ->queue()
        ->asImage();

    expect($pending)->toBeInstanceOf(PendingExecution::class);
    expect($pending->executionId)->toBeNull();
});

// ─── No DB errors when persistence disabled ─────────────────────────

it('TracksExecution no-ops when persistence disabled', function () {
    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();

    $harness = new class(executionId: 999)
    {
        use TracksExecution;

        public function __construct(public readonly ?int $executionId) {}

        public function transition(): void
        {
            $this->transitionToProcessing();
        }

        public function markFailed(Throwable $e): void
        {
            $this->markExecutionFailed($e);
        }
    };

    // Should not throw even though execution 999 doesn't exist
    $harness->transition();
    $harness->markFailed(new RuntimeException('test'));

    expect(true)->toBeTrue(); // No exception
});
