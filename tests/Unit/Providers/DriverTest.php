<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\VoiceTransport;
use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Responses\VoiceSession;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function createTestDriver(): Driver
{
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');
    $http = Mockery::mock(HttpClient::class);

    return new class($config, $http) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities;
        }

        public function name(): string
        {
            return 'test';
        }
    };
}

it('throws UnsupportedFeatureException for text', function () {
    createTestDriver()->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class, 'text');

it('throws UnsupportedFeatureException for stream', function () {
    createTestDriver()->stream(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class, 'text');

it('throws UnsupportedFeatureException for structured', function () {
    createTestDriver()->structured(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(UnsupportedFeatureException::class, 'text');

it('throws UnsupportedFeatureException for image', function () {
    createTestDriver()->image(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'image');

it('throws UnsupportedFeatureException for imageToText', function () {
    createTestDriver()->imageToText(new ImageRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'image');

it('throws UnsupportedFeatureException for audio', function () {
    createTestDriver()->audio(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for audioToText', function () {
    createTestDriver()->audioToText(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class, 'audio');

it('throws UnsupportedFeatureException for video', function () {
    createTestDriver()->video(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for videoToText', function () {
    createTestDriver()->videoToText(new VideoRequest('model', null, [], null, null, null));
})->throws(UnsupportedFeatureException::class, 'video');

it('throws UnsupportedFeatureException for embed', function () {
    createTestDriver()->embed(new EmbedRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class, 'embed');

it('throws UnsupportedFeatureException for moderate', function () {
    createTestDriver()->moderate(new ModerateRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class, 'moderate');

it('throws UnsupportedFeatureException for models', function () {
    createTestDriver()->models();
})->throws(UnsupportedFeatureException::class, 'models');

it('throws UnsupportedFeatureException for voices', function () {
    createTestDriver()->voices();
})->throws(UnsupportedFeatureException::class, 'voices');

it('throws UnsupportedFeatureException for validate on base driver', function () {
    createTestDriver()->validate();
})->throws(UnsupportedFeatureException::class, 'validate');

it('throws UnsupportedFeatureException for rerank', function () {
    createTestDriver()->rerank(new RerankRequest('model', 'query', ['doc1', 'doc2']));
})->throws(UnsupportedFeatureException::class, 'rerank');

it('throws UnsupportedFeatureException for voice session', function () {
    createTestDriver()->createVoiceSession(new VoiceRequest('model', null, null));
})->throws(UnsupportedFeatureException::class, 'voice');

it('throws UnsupportedFeatureException for voice connect', function () {
    $session = new VoiceSession(
        sessionId: 'test',
        provider: 'test',
        model: 'test',
        transport: VoiceTransport::WebSocket,
    );
    createTestDriver()->connectVoice($session);
})->throws(UnsupportedFeatureException::class, 'voice');

// ─── withHandler ────────────────────────────────────────────────────────────

it('withHandler returns a new instance', function () {
    $driver = createTestDriver();
    $handler = Mockery::mock(TextHandler::class);

    $newDriver = $driver->withHandler('text', $handler);

    expect($newDriver)->not->toBe($driver);
    expect($newDriver)->toBeInstanceOf(Driver::class);
});

it('withHandler does not affect original instance', function () {
    $driver = createTestDriver();
    $handler = Mockery::mock(TextHandler::class);

    $driver->withHandler('text', $handler);

    // Original driver should still throw UnsupportedFeatureException
    expect(fn () => $driver->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], [])))
        ->toThrow(UnsupportedFeatureException::class);
});

it('withHandler makes text handler available on bare driver', function () {
    $expectedResponse = new TextResponse(
        text: 'hello',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
    );

    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->once()->andReturn($expectedResponse);

    $driver = createTestDriver()->withHandler('text', $handler);
    $response = $driver->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));

    expect($response->text)->toBe('hello');
});

it('resolveHandler prefers override over default', function () {
    $overrideResponse = new TextResponse(
        text: 'from override',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
    );

    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->once()->andReturn($overrideResponse);

    // Create a driver that has a built-in text handler
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');
    $http = Mockery::mock(HttpClient::class);

    $builtInHandler = Mockery::mock(TextHandler::class);
    $builtInHandler->shouldNotReceive('text');

    $driver = new class($config, $http, $builtInHandler) extends Driver
    {
        public function __construct(
            ProviderConfig $config,
            HttpClient $http,
            private readonly TextHandler $builtIn,
        ) {
            parent::__construct($config, $http);
        }

        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities(text: true);
        }

        public function name(): string
        {
            return 'test-with-text';
        }

        protected function textHandler(): TextHandler
        {
            return $this->builtIn;
        }
    };

    $overridden = $driver->withHandler('text', $handler);
    $response = $overridden->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));

    expect($response->text)->toBe('from override');
});

it('multiple withHandler calls stack independently', function () {
    $textResponse = new TextResponse(
        text: 'text response',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
    );

    $embedResponse = new EmbeddingsResponse(
        embeddings: [[0.1, 0.2]],
        usage: new Usage(5, 0),
    );

    $textHandler = Mockery::mock(TextHandler::class);
    $textHandler->shouldReceive('text')->once()->andReturn($textResponse);

    $embedHandler = Mockery::mock(EmbedHandler::class);
    $embedHandler->shouldReceive('embed')->once()->andReturn($embedResponse);

    $driver = createTestDriver()
        ->withHandler('text', $textHandler)
        ->withHandler('embed', $embedHandler);

    $text = $driver->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
    $embed = $driver->embed(new EmbedRequest('model', 'text'));

    expect($text->text)->toBe('text response');
    expect($embed->embeddings)->toBe([[0.1, 0.2]]);
});

// ─── dispatch catches RequestException ──────────────────────────────────────

it('dispatch maps RequestException from handler to ProviderException', function () {
    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->andThrow(
        makeRequestExceptionForStatus(500)
    );

    $driver = createTestDriver()->withHandler('text', $handler);

    $driver->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(ProviderException::class);

it('dispatch maps 401 RequestException from handler to AuthenticationException', function () {
    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->andThrow(
        makeRequestExceptionForStatus(401)
    );

    $driver = createTestDriver()->withHandler('text', $handler);

    $driver->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(AuthenticationException::class);

it('dispatch maps 429 RequestException from handler to RateLimitException', function () {
    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->andThrow(
        makeRequestExceptionForStatus(429)
    );

    $driver = createTestDriver()->withHandler('text', $handler);

    $driver->text(new TextRequest('model', null, null, [], [], null, null, null, [], [], []));
})->throws(RateLimitException::class);

// ─── handleRequestException ─────────────────────────────────────────────────

function makeRequestExceptionForStatus(int $status): RequestException
{
    Http::fake(['*' => Http::response('error', $status)]);

    try {
        Http::get('https://api.test.com/fail')->throw();
    } catch (RequestException $e) {
        return $e;
    }

    throw new RuntimeException('Expected RequestException');
}

it('maps 401 to AuthenticationException', function () {
    createTestDriver()->handleRequestException('gpt-4o', makeRequestExceptionForStatus(401));
})->throws(AuthenticationException::class);

it('maps 403 to AuthorizationException', function () {
    createTestDriver()->handleRequestException('gpt-4o', makeRequestExceptionForStatus(403));
})->throws(AuthorizationException::class);

it('maps 429 to RateLimitException', function () {
    createTestDriver()->handleRequestException('gpt-4o', makeRequestExceptionForStatus(429));
})->throws(RateLimitException::class);

it('maps 500 to ProviderException', function () {
    createTestDriver()->handleRequestException('gpt-4o', makeRequestExceptionForStatus(500));
})->throws(ProviderException::class);
