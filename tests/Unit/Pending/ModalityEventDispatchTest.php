<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\TextCompleted;
use Atlasphp\Atlas\Events\TextStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

// ─── asText dispatches events ──────────────────────────────────────────────────

it('asText dispatches TextStarted and TextCompleted with correct properties', function () {
    Event::fake();

    Atlas::fake([
        TextResponseFake::make()->withText('hello'),
    ]);

    Atlas::text('openai', 'gpt-4o')->message('hi')->asText();

    Event::assertDispatched(TextStarted::class, fn (TextStarted $e) => $e->modality === Modality::Text
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );

    Event::assertDispatched(TextCompleted::class, fn (TextCompleted $e) => $e->modality === Modality::Text
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );
});

// ─── asStructured dispatches events ────────────────────────────────────────────

it('asStructured dispatches TextStarted and TextCompleted with Structured modality', function () {
    Event::fake();

    Atlas::fake();

    Atlas::text('openai', 'gpt-4o')->message('hi')->asStructured();

    Event::assertDispatched(TextStarted::class, fn (TextStarted $e) => $e->modality === Modality::Structured
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );

    Event::assertDispatched(TextCompleted::class, fn (TextCompleted $e) => $e->modality === Modality::Structured
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );
});

// ─── TextCompleted carries usage data ──────────────────────────────────────────

it('TextCompleted carries usage data', function () {
    Event::fake();

    Atlas::fake([
        TextResponseFake::make()
            ->withText('hello')
            ->withUsage(new Usage(42, 84)),
    ]);

    Atlas::text('openai', 'gpt-4o')->message('hi')->asText();

    Event::assertDispatched(TextCompleted::class, fn (TextCompleted $e) => $e->usage !== null
        && $e->usage->inputTokens === 42
        && $e->usage->outputTokens === 84
    );
});

// ─── Queued requests do NOT dispatch events ────────────────────────────────────

it('queued requests skip synchronous event dispatch path', function () {
    Event::fake();
    Queue::fake();

    Atlas::fake([
        TextResponseFake::make()->withText('queued'),
    ]);

    // queue() sets the queued flag — dispatchToQueue returns a PendingExecution
    // instead of executing inline, so TextStarted/TextCompleted do not fire
    // in the calling process.
    $result = Atlas::text('openai', 'gpt-4o')->message('hi')->queue()->asText();

    expect($result)->toBeInstanceOf(PendingExecution::class);

    Event::assertNotDispatched(TextStarted::class);
    Event::assertNotDispatched(TextCompleted::class);
});

// ─── Stream deferred TextCompleted ─────────────────────────────────────────────

it('TextCompleted fires after stream is consumed, not before', function () {
    Event::fake();

    Atlas::fake([
        StreamResponseFake::make()->withText('streamed text'),
    ]);

    $response = Atlas::text('openai', 'gpt-4o')->message('hi')->asStream();

    // TextStarted fires immediately when asStream() is called
    Event::assertDispatched(TextStarted::class, fn (TextStarted $e) => $e->modality === Modality::Stream);

    // TextCompleted should NOT have fired yet — the stream has not been consumed
    Event::assertNotDispatched(TextCompleted::class);

    // Consume the stream
    expect($response)->toBeInstanceOf(StreamResponse::class);
    iterator_to_array($response);

    // Now TextCompleted should have fired
    Event::assertDispatched(TextCompleted::class, fn (TextCompleted $e) => $e->modality === Modality::Stream
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );
});
