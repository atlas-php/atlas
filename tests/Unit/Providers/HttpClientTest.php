<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ProviderRequestCompleted;
use Atlasphp\Atlas\Events\ProviderRequestFailed;
use Atlasphp\Atlas\Events\ProviderRequestRetrying;
use Atlasphp\Atlas\Events\ProviderRequestStarted;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\RetryDecider;
use Atlasphp\Atlas\RequestConfig;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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

it('fires ProviderRequestStarted before the request', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $client->post('https://api.test.com/chat', [], ['model' => 'test'], 60);

    Event::assertDispatched(ProviderRequestStarted::class, function ($event) {
        return $event->url === 'https://api.test.com/chat';
    });
});

it('fires ProviderRequestCompleted after a successful response', function () {
    Http::fake([
        '*' => Http::response(['data' => 'value'], 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $client->post('https://api.test.com/chat', [], [], 60);

    Event::assertDispatched(ProviderRequestCompleted::class, function ($event) {
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

    Event::assertDispatched(ProviderRequestStarted::class);
    Event::assertDispatched(ProviderRequestCompleted::class);
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

    Event::assertDispatched(ProviderRequestStarted::class);
    Event::assertDispatched(ProviderRequestCompleted::class);
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

    Event::assertDispatched(ProviderRequestStarted::class);
    Event::assertDispatched(ProviderRequestCompleted::class);
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

it('sends a get raw request and returns body string', function () {
    Http::fake([
        'https://api.test.com/video' => Http::response('binary-video-data', 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $body = $client->getRaw('https://api.test.com/video', ['Authorization' => 'Bearer test'], 60);

    expect($body)->toBe('binary-video-data');

    Event::assertDispatched(ProviderRequestStarted::class);
    Event::assertDispatched(ProviderRequestCompleted::class);
});

it('fires ProviderRequestFailed on getRaw failure', function () {
    Http::fake([
        '*' => Http::response('error', 404),
    ]);

    Event::fake();

    $client = app(HttpClient::class);

    try {
        $client->getRaw('https://api.test.com/video', [], 60);
    } catch (RequestException) {
        // expected
    }

    Event::assertDispatched(ProviderRequestFailed::class);
});

it('sends a stream request and fires events', function () {
    Http::fake([
        '*' => Http::response('stream-data', 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $response = $client->stream('https://api.test.com/chat', [], ['model' => 'test'], 60);

    expect($response)->not->toBeNull();

    Event::assertDispatched(ProviderRequestStarted::class);
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

it('fires ProviderRequestCompleted on successful stream', function () {
    Http::fake([
        '*' => Http::response('stream-data', 200),
    ]);

    Event::fake();

    $client = app(HttpClient::class);
    $client->stream('https://api.test.com/chat', [], ['model' => 'test'], 60);

    Event::assertDispatched(ProviderRequestCompleted::class, function ($event) {
        return $event->url === 'https://api.test.com/chat';
    });
});

it('stream returns an Illuminate Response', function () {
    Http::fake([
        '*' => Http::response('stream-data', 200),
    ]);

    $client = app(HttpClient::class);
    $response = $client->stream('https://api.test.com/chat', [], [], 60);

    expect($response)->toBeInstanceOf(Response::class);
});

// ─── RETRY ──────────────────────────────────────────────────────────────────

it('post retries with RequestConfig and fires ProviderRequestRetrying', function () {
    Event::fake();

    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return $callCount === 1
            ? Http::response(['error' => 'server error'], 500)
            : Http::response(['data' => 'ok'], 200);
    });

    // Mock decider to allow one retry then stop
    $decider = new class extends RetryDecider
    {
        public int $retryCount = 0;

        public function shouldRetry(Throwable $e, RequestConfig $config, int $attempt): bool
        {
            $this->retryCount++;

            return $attempt <= 1;
        }

        public function waitMicroseconds(Throwable $e, int $attempt): int
        {
            return 0;
        }
    };

    $client = new HttpClient(app(Dispatcher::class), $decider);
    $config = new RequestConfig(60, 3, 2);

    $data = $client->post('https://api.test.com/chat', [], [], 60, $config);

    expect($data)->toBe(['data' => 'ok']);
    expect($callCount)->toBe(2);
    expect($decider->retryCount)->toBe(1);

    Event::assertDispatched(ProviderRequestRetrying::class, function ($event) {
        return $event->url === 'https://api.test.com/chat'
            && $event->attempt === 1
            && $event->waitMicroseconds === 0;
    });
});

it('post throws after retry limit exhausted', function () {
    $decider = new class extends RetryDecider
    {
        public function shouldRetry(Throwable $e, RequestConfig $config, int $attempt): bool
        {
            return false;
        }
    };

    $client = new HttpClient(app(Dispatcher::class), $decider);
    $config = new RequestConfig(60, 0, 0);

    Http::fake([
        '*' => Http::response(['error' => 'server error'], 500),
    ]);

    $client->post('https://api.test.com/chat', [], [], 60, $config);
})->throws(RequestException::class);

it('post does not retry when config is null', function () {
    Event::fake();

    Http::fake([
        '*' => Http::response(['error' => 'fail'], 500),
    ]);

    $client = app(HttpClient::class);

    try {
        $client->post('https://api.test.com/chat', [], [], 60, null);
    } catch (RequestException) {
        // expected
    }

    Event::assertNotDispatched(ProviderRequestRetrying::class);
});

it('post does not retry when retry is disabled', function () {
    Event::fake();

    Http::fake([
        '*' => Http::response(['error' => 'fail'], 500),
    ]);

    $client = app(HttpClient::class);
    $config = (new RequestConfig(60, 3, 2))->withoutRetry();

    try {
        $client->post('https://api.test.com/chat', [], [], 60, $config);
    } catch (RequestException) {
        // expected
    }

    Event::assertNotDispatched(ProviderRequestRetrying::class);
});

it('postRaw retries with RequestConfig', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return $callCount === 1
            ? Http::response('error', 500)
            : Http::response('binary-data', 200);
    });

    $decider = new class extends RetryDecider
    {
        public function shouldRetry(Throwable $e, RequestConfig $config, int $attempt): bool
        {
            return $attempt <= 1;
        }

        public function waitMicroseconds(Throwable $e, int $attempt): int
        {
            return 0;
        }
    };

    $client = new HttpClient(app(Dispatcher::class), $decider);
    $config = new RequestConfig(60, 3, 2);

    $body = $client->postRaw('https://api.test.com/audio', [], [], 60, $config);

    expect($body)->toBe('binary-data');
    expect($callCount)->toBe(2);
});
