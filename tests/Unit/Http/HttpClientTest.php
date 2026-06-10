<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestRetrying;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\RetryDecider;
use Atlasphp\Atlas\RequestConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function httpClient(): HttpClient
{
    return new HttpClient(app('events'), new RetryDecider);
}

it('returns decoded json and fires a completed event on success', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $completed = 0;
    Event::listen(ProviderRequestCompleted::class, function () use (&$completed): void {
        $completed++;
    });

    $data = httpClient()->post('https://api.test/x', [], ['a' => 1], 30);

    expect($data)->toBe(['ok' => true]);
    expect($completed)->toBe(1);
});

it('throws a RequestException on a failed response', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    httpClient()->post('https://api.test/x', [], [], 30);
})->throws(RequestException::class);

it('retries a transient 5xx when the request config enables error retry', function () {
    Http::fakeSequence()
        ->push('err', 503)
        ->push(['ok' => true], 200);

    $retries = 0;
    Event::listen(ProviderRequestRetrying::class, function () use (&$retries): void {
        $retries++;
    });

    $data = httpClient()->post('https://api.test/x', [], [], 30, new RequestConfig(30, 0, 2));

    expect($retries)->toBe(1);
    expect($data)->toBe(['ok' => true]);
});

it('does not retry when no request config is passed', function () {
    Http::fakeSequence()
        ->push('err', 503)
        ->push(['ok' => true], 200);

    httpClient()->post('https://api.test/x', [], [], 30);
})->throws(RequestException::class);

it('does not retry a permanent 4xx even with retry enabled', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        return Http::response('bad', 400);
    });

    try {
        httpClient()->post('https://api.test/x', [], [], 30, new RequestConfig(30, 3, 3));
    } catch (RequestException) {
        // expected
    }

    expect($attempts)->toBe(1);
});

it('retries a connection failure then rethrows when attempts are exhausted', function () {
    Http::fake(function (): void {
        throw new ConnectionException('cURL error 28: timed out');
    });

    $retries = 0;
    Event::listen(ProviderRequestRetrying::class, function () use (&$retries): void {
        $retries++;
    });

    $caught = null;

    try {
        httpClient()->post('https://api.test/x', [], [], 30, new RequestConfig(30, 0, 1));
    } catch (ConnectionException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(ConnectionException::class);
    expect($retries)->toBe(1);
});

it('applies an explicit timeout override but keeps the handler default otherwise', function () {
    $client = httpClient();
    $method = new ReflectionMethod($client, 'effectiveTimeout');
    $method->setAccessible(true);

    // No config → handler default stands.
    expect($method->invoke($client, 120, null))->toBe(120);

    // Config present but timeout not explicitly set → handler default stands
    // (so a 60s default never clobbers a 120s reasoning/media timeout).
    expect($method->invoke($client, 120, new RequestConfig(30, 3, 2)))->toBe(120);

    // Explicit ->withTimeout() → override wins.
    expect($method->invoke($client, 120, (new RequestConfig(30, 3, 2))->withTimeout(15)))->toBe(15);
});
