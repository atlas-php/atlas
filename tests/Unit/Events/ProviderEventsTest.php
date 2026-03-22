<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequestStarted;

// ─── ProviderRequestStarted ────────────────────────────────────────────────

it('ProviderRequestStarted stores url and body', function () {
    $event = new ProviderRequestStarted(
        url: 'https://api.openai.com/v1/chat/completions',
        body: ['model' => 'gpt-4', 'messages' => []],
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->body)->toBe(['model' => 'gpt-4', 'messages' => []]);
});

it('ProviderRequestStarted stores empty body', function () {
    $event = new ProviderRequestStarted(url: 'https://api.example.com', body: []);

    expect($event->url)->toBe('https://api.example.com')
        ->and($event->body)->toBe([]);
});

// ─── ProviderRequestCompleted ──────────────────────────────────────────────

it('ProviderRequestCompleted stores url and data', function () {
    $event = new ProviderRequestCompleted(
        url: 'https://api.openai.com/v1/chat/completions',
        data: ['id' => 'chatcmpl-123', 'choices' => []],
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->data)->toBe(['id' => 'chatcmpl-123', 'choices' => []]);
});

it('ProviderRequestCompleted stores empty data', function () {
    $event = new ProviderRequestCompleted(url: 'https://api.example.com', data: []);

    expect($event->url)->toBe('https://api.example.com')
        ->and($event->data)->toBe([]);
});

// ─── ProviderRequestFailed ─────────────────────────────────────────────────

it('ProviderRequestFailed stores url and response', function () {
    $event = new ProviderRequestFailed(
        url: 'https://api.openai.com/v1/chat/completions',
        response: ['error' => ['message' => 'Rate limit exceeded']],
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->response)->toBe(['error' => ['message' => 'Rate limit exceeded']]);
});

it('ProviderRequestFailed stores string response', function () {
    $event = new ProviderRequestFailed(url: 'https://api.example.com', response: 'Connection timeout');

    expect($event->url)->toBe('https://api.example.com')
        ->and($event->response)->toBe('Connection timeout');
});

it('ProviderRequestFailed stores null response', function () {
    $event = new ProviderRequestFailed(url: 'https://api.example.com', response: null);

    expect($event->url)->toBe('https://api.example.com')
        ->and($event->response)->toBeNull();
});
