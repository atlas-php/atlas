<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequesting;
use Atlasphp\Atlas\Events\ProviderResponded;
use Atlasphp\Atlas\Providers\HttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

it('sends a post request and returns json data', function () {
    Http::fake([
        'https://api.test.com/chat' => Http::response(['choices' => []], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $data = $client->post(
        'https://api.test.com/chat',
        ['Authorization' => 'Bearer test'],
        ['model' => 'gpt-4o'],
        60,
    );

    expect($data)->toBe(['choices' => []]);
});

it('fires ProviderRequesting before the request', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $client->post('https://api.test.com/chat', [], ['model' => 'test'], 60);

    Event::assertDispatched(ProviderRequesting::class, function ($event) {
        return $event->url === 'https://api.test.com/chat';
    });
});

it('fires ProviderResponded after a successful response', function () {
    Http::fake([
        '*' => Http::response(['data' => 'value'], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $client->post('https://api.test.com/chat', [], [], 60);

    Event::assertDispatched(ProviderResponded::class, function ($event) {
        return $event->data === ['data' => 'value'];
    });
});

it('fires ProviderRequestFailed on failure', function () {
    Http::fake([
        '*' => Http::response(['error' => 'bad'], 500),
    ]);

    Event::fake();

    $client = app(HttpClient::class);

    try {
        $client->post('https://api.test.com/chat', [], [], 60);
    } catch (RequestException) {
        // expected
    }

    Event::assertDispatched(ProviderRequestFailed::class, function ($event) {
        return $event->url === 'https://api.test.com/chat';
    });
});

// ─── GET ─────────────────────────────────────────────────────────────────────

it('sends a get request and returns json data', function () {
    Http::fake([
        'https://api.test.com/models' => Http::response(['data' => [['id' => 'gpt-4o']]], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $data = $client->get('https://api.test.com/models', ['Authorization' => 'Bearer test'], 60);

    expect($data)->toBe(['data' => [['id' => 'gpt-4o']]]);

    Event::assertDispatched(ProviderRequesting::class);
    Event::assertDispatched(ProviderResponded::class);
});

it('fires ProviderRequestFailed on get failure', function () {
    Http::fake([
        '*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    Event::fake();

    $client = app(HttpClient::class);

    try {
        $client->get('https://api.test.com/models', [], 60);
    } catch (RequestException) {
        // expected
    }

    Event::assertDispatched(ProviderRequestFailed::class);
});

// ─── POST RAW ────────────────────────────────────────────────────────────────

it('sends a postRaw request and returns raw body string', function () {
    Http::fake([
        'https://api.test.com/audio/speech' => Http::response('binary-audio-data', 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $body = $client->postRaw('https://api.test.com/audio/speech', [], ['model' => 'tts-1'], 60);

    expect($body)->toBe('binary-audio-data');

    Event::assertDispatched(ProviderRequesting::class);
    Event::assertDispatched(ProviderResponded::class);
});

it('fires ProviderRequestFailed on postRaw failure', function () {
    Http::fake([
        '*' => Http::response('error', 500),
    ]);

    Event::fake();

    $client = app(HttpClient::class);

    try {
        $client->postRaw('https://api.test.com/audio/speech', [], [], 60);
    } catch (RequestException) {
        // expected
    }

    Event::assertDispatched(ProviderRequestFailed::class);
});

// ─── POST MULTIPART ──────────────────────────────────────────────────────────

it('sends a postMultipart request and returns json data', function () {
    Http::fake([
        'https://api.test.com/audio/transcriptions' => Http::response(['text' => 'hello'], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $data = $client->postMultipart(
        'https://api.test.com/audio/transcriptions',
        ['Authorization' => 'Bearer test'],
        ['model' => 'whisper-1'],
        [['name' => 'file', 'contents' => 'fake-audio', 'filename' => 'audio.mp3']],
        60,
    );

    expect($data)->toBe(['text' => 'hello']);

    Event::assertDispatched(ProviderRequesting::class);
    Event::assertDispatched(ProviderResponded::class);
});

it('fires ProviderRequestFailed on postMultipart failure', function () {
    Http::fake([
        '*' => Http::response(['error' => 'bad'], 400),
    ]);

    Event::fake();

    $client = app(HttpClient::class);

    try {
        $client->postMultipart('https://api.test.com/audio/transcriptions', [], [], [], 60);
    } catch (RequestException) {
        // expected
    }

    Event::assertDispatched(ProviderRequestFailed::class);
});

// ─── STREAM ──────────────────────────────────────────────────────────────────

it('fires ProviderRequestFailed on stream failure', function () {
    Http::fake([
        '*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    Event::fake();

    $client = app(HttpClient::class);

    try {
        $client->stream('https://api.test.com/chat', [], [], 60);
    } catch (RequestException) {
        // expected
    }

    Event::assertDispatched(ProviderRequestFailed::class, function ($event) {
        return $event->url === 'https://api.test.com/chat';
    });
});
