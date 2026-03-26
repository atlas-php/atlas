<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Queue\PendingExecution;
use Illuminate\Support\Facades\Queue;

class QueuedModalitiesMinimalAgent extends Agent
{
    public function key(): string
    {
        return 'queued-minimal';
    }
}

beforeEach(function () {
    Atlas::fake();
    Queue::fake();
    config(['atlas.defaults.text' => ['provider' => 'openai', 'model' => 'gpt-4o-mini']]);
    AtlasConfig::refresh();
});

// ─── Text ────────────────────────────────────────────────────────────────────

it('queues text asText', function () {
    $result = Atlas::text('openai', 'gpt-5')->message('Hello')->queue()->asText();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues text asStream', function () {
    $result = Atlas::text('openai', 'gpt-5')->message('Hello')->queue()->asStream();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues text asStructured', function () {
    $result = Atlas::text('openai', 'gpt-5')->message('Hello')->queue()->asStructured();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Image ───────────────────────────────────────────────────────────────────

it('queues image asImage', function () {
    $result = Atlas::image('openai', 'dall-e-3')->instructions('sunset')->queue()->asImage();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues image asText', function () {
    $result = Atlas::image('openai', 'gpt-5')->instructions('describe')->queue()->asText();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Audio ───────────────────────────────────────────────────────────────────

it('queues audio asAudio', function () {
    $result = Atlas::audio('openai', 'tts-1')->instructions('Hello')->queue()->asAudio();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues audio asText', function () {
    $result = Atlas::audio('openai', 'whisper-1')->instructions('transcribe')->queue()->asText();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Video ───────────────────────────────────────────────────────────────────

it('queues video asVideo', function () {
    $result = Atlas::video('openai', 'sora')->instructions('sunset timelapse')->queue()->asVideo();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues video asText', function () {
    $result = Atlas::video('openai', 'gpt-5')->instructions('describe')->queue()->asText();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Embed ───────────────────────────────────────────────────────────────────

it('queues embed asEmbeddings', function () {
    $result = Atlas::embed('openai', 'text-embedding-3-small')->fromInput('test')->queue()->asEmbeddings();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Moderate ────────────────────────────────────────────────────────────────

it('queues moderate asModeration', function () {
    $result = Atlas::moderate('openai', 'omni-moderation')->fromInput('test content')->queue()->asModeration();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Rerank ──────────────────────────────────────────────────────────────────

it('queues rerank asReranked', function () {
    $result = Atlas::rerank('openai', 'rerank-v3')
        ->query('test query')
        ->documents(['doc1', 'doc2'])
        ->queue()
        ->asReranked();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

// ─── Agent ──────────────────────────────────────────────────────────────────

it('queues agent asText', function () {
    app(AgentRegistry::class)->register(QueuedModalitiesMinimalAgent::class);

    $result = Atlas::agent('queued-minimal')->message('Hello')->queue()->asText();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues agent asStream', function () {
    app(AgentRegistry::class)->register(QueuedModalitiesMinimalAgent::class);

    $result = Atlas::agent('queued-minimal')->message('Hello')->queue()->asStream();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});

it('queues agent asStructured', function () {
    app(AgentRegistry::class)->register(QueuedModalitiesMinimalAgent::class);

    $result = Atlas::agent('queued-minimal')->message('Hello')->queue()->asStructured();
    expect($result)->toBeInstanceOf(PendingExecution::class);
});
