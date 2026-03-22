<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Queue\PendingExecution;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

// ─── asText dispatches events ──────────────────────────────────────────────────

it('asText dispatches ModalityStarted and ModalityCompleted with correct properties', function () {
    Event::fake();

    Atlas::fake([
        TextResponseFake::make()->withText('hello'),
    ]);

    Atlas::text('openai', 'gpt-4o')->message('hi')->asText();

    Event::assertDispatched(ModalityStarted::class, fn (ModalityStarted $e) => $e->modality === Modality::Text
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );

    Event::assertDispatched(ModalityCompleted::class, fn (ModalityCompleted $e) => $e->modality === Modality::Text
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );
});

// ─── asStructured dispatches events ────────────────────────────────────────────

it('asStructured dispatches ModalityStarted and ModalityCompleted with Structured modality', function () {
    Event::fake();

    Atlas::fake();

    Atlas::text('openai', 'gpt-4o')->message('hi')->asStructured();

    Event::assertDispatched(ModalityStarted::class, fn (ModalityStarted $e) => $e->modality === Modality::Structured
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );

    Event::assertDispatched(ModalityCompleted::class, fn (ModalityCompleted $e) => $e->modality === Modality::Structured
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );
});

// ─── ModalityCompleted carries usage data ──────────────────────────────────────

it('ModalityCompleted carries usage data', function () {
    Event::fake();

    Atlas::fake([
        TextResponseFake::make()
            ->withText('hello')
            ->withUsage(new Usage(42, 84)),
    ]);

    Atlas::text('openai', 'gpt-4o')->message('hi')->asText();

    Event::assertDispatched(ModalityCompleted::class, fn (ModalityCompleted $e) => $e->usage !== null
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
    // instead of executing inline, so ModalityStarted/ModalityCompleted do not fire
    // in the calling process.
    $result = Atlas::text('openai', 'gpt-4o')->message('hi')->queue()->asText();

    expect($result)->toBeInstanceOf(PendingExecution::class);

    Event::assertNotDispatched(ModalityStarted::class);
    Event::assertNotDispatched(ModalityCompleted::class);
});

// ─── Stream deferred ModalityCompleted ──────────────────────────────────────────

it('ModalityCompleted fires after stream is consumed, not before', function () {
    Event::fake();

    Atlas::fake([
        StreamResponseFake::make()->withText('streamed text'),
    ]);

    $response = Atlas::text('openai', 'gpt-4o')->message('hi')->asStream();

    // ModalityStarted fires immediately when asStream() is called
    Event::assertDispatched(ModalityStarted::class, fn (ModalityStarted $e) => $e->modality === Modality::Stream);

    // ModalityCompleted should NOT have fired yet — the stream has not been consumed
    Event::assertNotDispatched(ModalityCompleted::class);

    // Consume the stream
    expect($response)->toBeInstanceOf(StreamResponse::class);
    iterator_to_array($response);

    // Now ModalityCompleted should have fired
    Event::assertDispatched(ModalityCompleted::class, fn (ModalityCompleted $e) => $e->modality === Modality::Stream
        && $e->provider === 'openai'
        && $e->model === 'gpt-4o'
    );
});
