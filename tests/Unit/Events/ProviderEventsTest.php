<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequestRetrying;
use Atlasphp\Atlas\Events\ProviderRequestStarted;
use Illuminate\Http\Client\Response;

// ─── ProviderRequestStarted ────────────────────────────────────────────────

it('ProviderRequestStarted stores url, body, and method', function () {
    $event = new ProviderRequestStarted(
        url: 'https://api.openai.com/v1/chat/completions',
        body: ['model' => 'gpt-4', 'messages' => []],
        method: 'POST',
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->body)->toBe(['model' => 'gpt-4', 'messages' => []])
        ->and($event->method)->toBe('POST');
});

it('ProviderRequestStarted defaults method to POST', function () {
    $event = new ProviderRequestStarted(url: 'https://api.example.com', body: []);

    expect($event->method)->toBe('POST');
});

it('ProviderRequestStarted stores GET method', function () {
    $event = new ProviderRequestStarted(url: 'https://api.example.com', body: [], method: 'GET');

    expect($event->method)->toBe('GET');
});

// ─── ProviderRequestCompleted ──────────────────────────────────────────────

it('ProviderRequestCompleted stores url, data, and statusCode', function () {
    $event = new ProviderRequestCompleted(
        url: 'https://api.openai.com/v1/chat/completions',
        data: ['id' => 'chatcmpl-123', 'choices' => []],
        statusCode: 200,
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->data)->toBe(['id' => 'chatcmpl-123', 'choices' => []])
        ->and($event->statusCode)->toBe(200);
});

it('ProviderRequestCompleted defaults statusCode to 200', function () {
    $event = new ProviderRequestCompleted(url: 'https://api.example.com', data: []);

    expect($event->statusCode)->toBe(200);
});

it('ProviderRequestCompleted stores non-200 statusCode', function () {
    $event = new ProviderRequestCompleted(url: 'https://api.example.com', data: [], statusCode: 201);

    expect($event->statusCode)->toBe(201);
});

// ─── ProviderRequestFailed ─────────────────────────────────────────────────

it('ProviderRequestFailed stores url and typed response', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(429);

    $event = new ProviderRequestFailed(
        url: 'https://api.openai.com/v1/chat/completions',
        response: $response,
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->response)->toBe($response)
        ->and($event->response->status())->toBe(429);
});

// ─── ProviderRequestRetrying ──────────────────────────────────────────────

it('ProviderRequestRetrying stores url, exception, attempt, and wait', function () {
    $exception = new RuntimeException('Connection timed out');

    $event = new ProviderRequestRetrying(
        url: 'https://api.openai.com/v1/chat/completions',
        exception: $exception,
        attempt: 2,
        waitMicroseconds: 1_000_000,
    );

    expect($event->url)->toBe('https://api.openai.com/v1/chat/completions')
        ->and($event->exception)->toBe($exception)
        ->and($event->attempt)->toBe(2)
        ->and($event->waitMicroseconds)->toBe(1_000_000);
});

it('ProviderRequestRetrying stores first attempt with zero wait', function () {
    $event = new ProviderRequestRetrying(
        url: 'https://api.example.com',
        exception: new RuntimeException('Server error'),
        attempt: 1,
        waitMicroseconds: 0,
    );

    expect($event->attempt)->toBe(1)
        ->and($event->waitMicroseconds)->toBe(0);
});
