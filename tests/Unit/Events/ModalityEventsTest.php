<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\AudioCompleted;
use Atlasphp\Atlas\Events\AudioStarted;
use Atlasphp\Atlas\Events\EmbeddingsCompleted;
use Atlasphp\Atlas\Events\EmbeddingsStarted;
use Atlasphp\Atlas\Events\ImageCompleted;
use Atlasphp\Atlas\Events\ImageStarted;
use Atlasphp\Atlas\Events\ModerationCompleted;
use Atlasphp\Atlas\Events\ModerationStarted;
use Atlasphp\Atlas\Events\RerankCompleted;
use Atlasphp\Atlas\Events\RerankStarted;
use Atlasphp\Atlas\Events\TextCompleted;
use Atlasphp\Atlas\Events\TextStarted;
use Atlasphp\Atlas\Events\VideoCompleted;
use Atlasphp\Atlas\Events\VideoStarted;
use Atlasphp\Atlas\Responses\Usage;

// ─── TextStarted ───────────────────────────────────────────────────────────

it('TextStarted stores modality, provider, and model', function () {
    $event = new TextStarted(
        modality: Modality::Text,
        provider: 'openai',
        model: 'gpt-4',
    );

    expect($event->modality)->toBe(Modality::Text)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4');
});

// ─── TextCompleted ─────────────────────────────────────────────────────────

it('TextCompleted stores modality, provider, and model', function () {
    $event = new TextCompleted(
        modality: Modality::Text,
        provider: 'openai',
        model: 'gpt-4',
    );

    expect($event->modality)->toBe(Modality::Text)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4')
        ->and($event->usage)->toBeNull();
});

// ─── ImageStarted ──────────────────────────────────────────────────────────

it('ImageStarted stores modality, provider, and model', function () {
    $event = new ImageStarted(
        modality: Modality::Image,
        provider: 'openai',
        model: 'dall-e-3',
    );

    expect($event->modality)->toBe(Modality::Image)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('dall-e-3');
});

// ─── ImageCompleted ────────────────────────────────────────────────────────

it('ImageCompleted stores modality, provider, and model', function () {
    $event = new ImageCompleted(
        modality: Modality::Image,
        provider: 'openai',
        model: 'dall-e-3',
    );

    expect($event->modality)->toBe(Modality::Image)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('dall-e-3')
        ->and($event->usage)->toBeNull();
});

// ─── AudioStarted ──────────────────────────────────────────────────────────

it('AudioStarted stores modality, provider, and model', function () {
    $event = new AudioStarted(
        modality: Modality::Audio,
        provider: 'openai',
        model: 'whisper-1',
    );

    expect($event->modality)->toBe(Modality::Audio)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('whisper-1');
});

// ─── AudioCompleted ────────────────────────────────────────────────────────

it('AudioCompleted stores modality, provider, and model', function () {
    $event = new AudioCompleted(
        modality: Modality::Audio,
        provider: 'openai',
        model: 'whisper-1',
    );

    expect($event->modality)->toBe(Modality::Audio)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('whisper-1')
        ->and($event->usage)->toBeNull();
});

// ─── VideoStarted ──────────────────────────────────────────────────────────

it('VideoStarted stores modality, provider, and model', function () {
    $event = new VideoStarted(
        modality: Modality::Video,
        provider: 'google',
        model: 'veo-2',
    );

    expect($event->modality)->toBe(Modality::Video)
        ->and($event->provider)->toBe('google')
        ->and($event->model)->toBe('veo-2');
});

// ─── VideoCompleted ────────────────────────────────────────────────────────

it('VideoCompleted stores modality, provider, and model', function () {
    $event = new VideoCompleted(
        modality: Modality::Video,
        provider: 'google',
        model: 'veo-2',
    );

    expect($event->modality)->toBe(Modality::Video)
        ->and($event->provider)->toBe('google')
        ->and($event->model)->toBe('veo-2')
        ->and($event->usage)->toBeNull();
});

// ─── EmbeddingsStarted ────────────────────────────────────────────────────

it('EmbeddingsStarted stores modality, provider, and model', function () {
    $event = new EmbeddingsStarted(
        modality: Modality::Embed,
        provider: 'openai',
        model: 'text-embedding-3-small',
    );

    expect($event->modality)->toBe(Modality::Embed)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('text-embedding-3-small');
});

// ─── EmbeddingsCompleted ───────────────────────────────────────────────────

it('EmbeddingsCompleted stores modality, provider, and model', function () {
    $event = new EmbeddingsCompleted(
        modality: Modality::Embed,
        provider: 'openai',
        model: 'text-embedding-3-small',
    );

    expect($event->modality)->toBe(Modality::Embed)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('text-embedding-3-small')
        ->and($event->usage)->toBeNull();
});

// ─── ModerationStarted ────────────────────────────────────────────────────

it('ModerationStarted stores modality, provider, and model', function () {
    $event = new ModerationStarted(
        modality: Modality::Moderate,
        provider: 'openai',
        model: 'omni-moderation-latest',
    );

    expect($event->modality)->toBe(Modality::Moderate)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('omni-moderation-latest');
});

// ─── ModerationCompleted ───────────────────────────────────────────────────

it('ModerationCompleted stores modality, provider, and model', function () {
    $event = new ModerationCompleted(
        modality: Modality::Moderate,
        provider: 'openai',
        model: 'omni-moderation-latest',
    );

    expect($event->modality)->toBe(Modality::Moderate)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('omni-moderation-latest')
        ->and($event->usage)->toBeNull();
});

// ─── RerankStarted ─────────────────────────────────────────────────────────

it('RerankStarted stores modality, provider, and model', function () {
    $event = new RerankStarted(
        modality: Modality::Rerank,
        provider: 'cohere',
        model: 'rerank-v3.5',
    );

    expect($event->modality)->toBe(Modality::Rerank)
        ->and($event->provider)->toBe('cohere')
        ->and($event->model)->toBe('rerank-v3.5');
});

// ─── RerankCompleted ───────────────────────────────────────────────────────

it('RerankCompleted stores modality, provider, and model', function () {
    $event = new RerankCompleted(
        modality: Modality::Rerank,
        provider: 'cohere',
        model: 'rerank-v3.5',
    );

    expect($event->modality)->toBe(Modality::Rerank)
        ->and($event->provider)->toBe('cohere')
        ->and($event->model)->toBe('rerank-v3.5')
        ->and($event->usage)->toBeNull();
});

// ─── TextCompleted with usage ─────────────────────────────────────────────

it('TextCompleted carries usage when provided', function () {
    $usage = new Usage(50, 100);
    $event = new TextCompleted(
        modality: Modality::Text,
        provider: 'openai',
        model: 'gpt-4o',
        usage: $usage,
    );

    expect($event->usage)->toBe($usage)
        ->and($event->usage->inputTokens)->toBe(50);
});
