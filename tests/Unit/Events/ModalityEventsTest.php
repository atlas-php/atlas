<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Modality;
use Atlasphp\Atlas\Events\ModalityCompleted;
use Atlasphp\Atlas\Events\ModalityStarted;
use Atlasphp\Atlas\Responses\Usage;

// ─── ModalityStarted ──────────────────────────────────────────────────────

it('ModalityStarted stores modality, provider, and model', function () {
    $event = new ModalityStarted(
        modality: Modality::Text,
        provider: 'openai',
        model: 'gpt-4',
    );

    expect($event->modality)->toBe(Modality::Text)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4');
});

it('ModalityStarted works with all modality types', function (Modality $modality) {
    $event = new ModalityStarted(
        modality: $modality,
        provider: 'test',
        model: 'test-model',
    );

    expect($event->modality)->toBe($modality);
})->with([
    Modality::Text,
    Modality::Stream,
    Modality::Structured,
    Modality::Image,
    Modality::ImageToText,
    Modality::Audio,
    Modality::AudioToText,
    Modality::Video,
    Modality::VideoToText,
    Modality::Embed,
    Modality::Moderate,
    Modality::Rerank,
]);

// ─── ModalityCompleted ────────────────────────────────────────────────────

it('ModalityCompleted stores modality, provider, and model with null usage', function () {
    $event = new ModalityCompleted(
        modality: Modality::Image,
        provider: 'openai',
        model: 'dall-e-3',
    );

    expect($event->modality)->toBe(Modality::Image)
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('dall-e-3')
        ->and($event->usage)->toBeNull();
});

it('ModalityCompleted carries usage when provided', function () {
    $usage = new Usage(50, 100);
    $event = new ModalityCompleted(
        modality: Modality::Text,
        provider: 'openai',
        model: 'gpt-4o',
        usage: $usage,
    );

    expect($event->usage)->toBe($usage)
        ->and($event->usage->inputTokens)->toBe(50);
});

it('ModalityCompleted works with all modality types', function (Modality $modality) {
    $event = new ModalityCompleted(
        modality: $modality,
        provider: 'test',
        model: 'test-model',
    );

    expect($event->modality)->toBe($modality);
})->with([
    Modality::Text,
    Modality::Stream,
    Modality::Structured,
    Modality::Image,
    Modality::ImageToText,
    Modality::Audio,
    Modality::AudioToText,
    Modality::Video,
    Modality::VideoToText,
    Modality::Embed,
    Modality::Moderate,
    Modality::Rerank,
]);
