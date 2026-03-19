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
